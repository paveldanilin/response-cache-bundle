<?php

namespace Pada\ResponseCacheBundle\Command;


use Pada\Reflection\Scanner\ClassInfo;
use Pada\Reflection\Scanner\ScannerInterface;
use Pada\ResponseCacheBundle\Controller\Annotation\Cacheable;
use Pada\ResponseCacheBundle\Controller\Annotation\CacheEvict;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DebugCommand extends Command
{
    protected static $defaultName = 'debug:response-cache';

    private ScannerInterface $reflectionScanner;
    private string $scanDir;

    public function setScanDir(string $scanDir): void
    {
        $this->scanDir = $scanDir;
    }

    public function setReflectionScanner(ScannerInterface $reflectionScanner): void
    {
        $this->reflectionScanner = $reflectionScanner;
    }

    protected function configure(): void
    {
        $this->setDescription('Shows controllers and actions that use the @Cacheable or @CacheEvict annotation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = new Table($output);
        $table->setHeaders(['Annotation', 'Class', 'Method']);
        $rows = [];

        /** @var ClassInfo $classInfo */
        foreach ($this->reflectionScanner->in($this->scanDir) as $classInfo) {

            foreach ($classInfo->getMethodNames() as $methodName) {
                foreach ($classInfo->getMethodAnnotations($methodName) as $methodAnnotation) {
                    $annotationName = null;
                    if ($methodAnnotation instanceof Cacheable) {
                        $annotationName = 'Cacheable';
                    } else if ($methodAnnotation instanceof CacheEvict) {
                        $annotationName = 'CacheEvict';
                    }
                    if (!empty($annotationName)) {
                        $rows[] = [
                            $annotationName,
                            $classInfo->getReflection()->getName(),
                            $methodName,
                        ];
                    }
                }
            }
        }

        $table->setRows($rows)->render();

        return 0;
    }
}

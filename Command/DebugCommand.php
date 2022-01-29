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
use Symfony\Component\Routing\Annotation\Route;

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
        $table->setHeaders(['Annotation', 'Pool', 'Key', 'Controller', 'Route', 'Options']);
        $rows = [];

        /** @var ClassInfo $classInfo */
        foreach ($this->reflectionScanner->in($this->scanDir) as $classInfo) {
            foreach ($classInfo->getMethodNames() as $methodName) {
                $info = $this->getInfo($classInfo, $methodName);
                if (\array_key_exists('annotation', $info)) {
                    $rows[] = [
                        "<info>{$info['annotation']}</info>",
                        $info['pool'],
                        $info['key'],
                        $classInfo->getReflection()->getName() . '::' . $methodName,
                        $info['route'],
                        $info['options'],
                    ];
                }
            }
        }

        $table->setRows($rows)->render();

        return 0;
    }

    private function getInfo(ClassInfo $classInfo, string $methodName): array
    {
        $info = [];
        foreach ($classInfo->getMethodAnnotations($methodName) as $methodAnnotation) {
            if ($methodAnnotation instanceof Cacheable) {
                $info['annotation'] = 'Cacheable';
                $info['pool'] = $methodAnnotation->pool;
                $info['key'] = $methodAnnotation->key;
                $options = [];
                $options[] = "<info>ttl</info>=$methodAnnotation->ttl";
                $options[] = "<info>condition</info>=$methodAnnotation->condition";
                $info['options'] = \implode('; ', $options);
            } else if ($methodAnnotation instanceof CacheEvict) {
                $info['annotation'] = 'CacheEvict';
                $info['pool'] = $methodAnnotation->pool;
                $info['key'] = $methodAnnotation->key;
            } else if ($methodAnnotation instanceof Route) {
                $info['route'] = '[' . \implode(',', $methodAnnotation->getMethods()) . '] ' . $methodAnnotation->getPath();
            }
        }
        return $info;
    }
}

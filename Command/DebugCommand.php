<?php

namespace Pada\ResponseCacheBundle\Command;


use Pada\Reflection\Scanner\ClassInfo;
use Pada\Reflection\Scanner\ScannerInterface;
use Pada\ResponseCacheBundle\Controller\Annotation\Cacheable;
use Pada\ResponseCacheBundle\Controller\Annotation\CacheEvict;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $this->addOption('options', 'o', InputOption::VALUE_NONE, 'Display options');
        $this->addOption('controller', 'c', InputOption::VALUE_NONE, 'Display controller and action');
        $this->addOption('route', 'r', InputOption::VALUE_NONE, 'Display route');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $optController = false !== $input->getOption('controller');
        $optOptions = false !== $input->getOption('options');
        $optRoute = false !== $input->getOption('route');

        if (!$optController && !$optRoute) {
            $optRoute = true;
        }

        $headers = ['Annotation', 'Pool', 'Key'];

        if ($optController) {
            $headers[] = 'Controller';
        }
        if ($optRoute) {
            $headers[] = 'Route';
        }
        if ($optOptions) {
            $headers[] = 'Options';
        }

        $table = new Table($output);
        $table->setHeaders($headers);
        $rows = [];

        /** @var ClassInfo $classInfo */
        foreach ($this->reflectionScanner->in($this->scanDir) as $classInfo) {
            foreach ($classInfo->getMethodNames() as $methodName) {
                $info = $this->getInfo($classInfo, $methodName);
                if (\array_key_exists('annotation', $info)) {
                    $row = [
                        "<info>{$info['annotation']}</info>",
                        $info['pool'] ?? '',
                        $info['key'] ?? ''
                    ];
                    if ($optController) {
                        $row[] = $classInfo->getReflection()->getName() . '::' . $methodName;
                    }
                    if ($optRoute) {
                        $row[] = $info['route'] ?? '';
                    }
                    if ($optOptions) {
                        $row[] = $info['options'] ?? '';
                    }
                    $rows[] = $row;
                }
            }
        }

        $table->setRows($rows)
            ->render();

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

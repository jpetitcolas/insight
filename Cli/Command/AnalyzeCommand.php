<?php

/*
 * This file is part of the SensioLabsInsight package.
 *
 * (c) SensioLabs <contact@sensiolabs.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SensioLabs\Insight\Cli\Command;

use SensioLabs\Insight\Cli\Helper\DescriptorHelper;
use SensioLabs\Insight\Sdk\Model\Analysis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyzeCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('analyze')
            ->addArgument('project-uuid', InputArgument::REQUIRED)
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'To output in other formats', 'txt')
            ->setDescription('Analyze a project')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectUuid = $input->getArgument('project-uuid');
        $api = $this->getApplication()->getApi();
        $analysis = $api->triggerAnalyse($projectUuid);

        $chars = array('-', '\\', '|', '/');
        $position = 0;
        while (true) {
            // we don't check the status too often
            if ($position % 5) {
                $analysis = $api->getAnalysisStatus($projectUuid, $analysis->getId());
            }
            $output->write(sprintf("%s %-80s\r", $chars[$position++ % 4], $analysis->getStatusMessage()));

            if ($analysis->isFinished()) {
                break;
            }

            usleep(200000);
        }

        $analysis = $api->getAnalysis($projectUuid, $analysis->getId());
        if ($analysis->isFailed()) {
            $output->writeln(sprintf('There was an error: "%s"', $analysis->getFailureMessage()));

            return 1;
        }

        $helper = new DescriptorHelper($api->getSerializer());
        $helper->describe($output, $analysis, $input->getOption('format'));

        if ('txt' === $input->getOption('format') && OutputInterface::VERBOSITY_VERBOSE > $output->getVerbosity()) {
            $output->writeln('');
            $output->writeln(sprintf('Run <comment>%s %s %s -v</comment> to get the full report', $_SERVER['PHP_SELF'], 'analysis', $projectUuid));
        }
    }
}
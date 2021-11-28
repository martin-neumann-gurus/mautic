<?php

namespace Mautic\MarketplaceBundle\Command;

use Exception;
use InvalidArgumentException;
use Mautic\MarketplaceBundle\Exception\ApiException;
use Mautic\MarketplaceBundle\Exception\InstallException;
use Mautic\MarketplaceBundle\Model\PackageModel;
use Mautic\MarketplaceBundle\Service\Composer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends Command
{
    public const NAME = 'mautic:marketplace:install';

    private Composer $composer;
    private PackageModel $packageModel;

    public function __construct(Composer $composer, PackageModel $packageModel)
    {
        parent::__construct();
        $this->composer     = $composer;
        $this->packageModel = $packageModel;
    }

    protected function configure(): void
    {
        $this->setName(self::NAME);
        $this->setDescription('Installs a plugin that is available at Packagist.org');
        $this->addArgument('package', InputArgument::REQUIRED, 'The Packagist package to install (e.g. mautic/example-plugin)');
        $this->addOption('dry-run', null, null, 'Simulate the installation of the package. Doesn\'t actually install it.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $packageName = $input->getArgument('package');
        $dryRun      = true === $input->getOption('dry-run') ? true : false;

        try {
            $package = $this->packageModel->getPackageDetail($packageName);
        } catch (ApiException $e) {
            if (404 === $e->getCode()) {
                throw new InvalidArgumentException('Given package '.$packageName.' does not exist in Packagist. Please check the name for typos.');
            } else {
                throw new Exception('Error while trying to get package details: '.$e->getMessage());
            }
        }

        $type = $package->versions[0]->type ?? null;

        if (empty($type) || 'mautic-plugin' !== $type) {
            throw new Exception('Package type is not mautic-plugin. Cannot install this plugin.');
        }

        if ($dryRun) {
            $output->writeLn('Note: dry-running this installation!');
        }

        $output->writeln('Installing '.$input->getArgument('package').', this might take a while...');
        $result = $this->composer->install($input->getArgument('package'), $dryRun);

        if (0 !== $result->exitCode) {
            throw new InstallException('Error while installing this plugin: '.$result->output);
        }

        $output->writeln('All done! '.$input->getArgument('package').' has successfully been installed.');

        return 0;
    }
}

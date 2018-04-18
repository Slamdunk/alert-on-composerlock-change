<?php

declare(strict_types=1);

namespace Slam\RunComposerInstallAlert;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

final class Installer implements PluginInterface, EventSubscriberInterface
{
    const POST_CHECKOUT_FILENAME = 'post-checkout';
    const POST_MERGE_FILENAME    = 'post-merge';

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        // Nothing to do here, as all features are provided through event listeners
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'installGitHooks',
            ScriptEvents::POST_UPDATE_CMD  => 'installGitHooks',
        ];
    }

    /**
     * @throws \RuntimeException
     */
    public static function installGitHooks(Event $composerEvent): void
    {
        $composer = $composerEvent->getComposer();
        $config   = $composer->getConfig();
        $io       = $composerEvent->getIO();

        if (false !== \strpos($config->get('vendor-dir', Config::RELATIVE_PATHS), \DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('This plugin only supports standard composer folder structure');
        }
        $gitDir = \dirname($config->get('vendor-dir')) . \DIRECTORY_SEPARATOR . '.git';
        if (! \is_dir($gitDir) || ! \is_readable($gitDir)) {
            throw new \RuntimeException('This plugin only supports git, and must be on project root');
        }
        $hookDir = $gitDir . \DIRECTORY_SEPARATOR . 'hooks';
        if (! \is_dir($hookDir)) {
            \mkdir($hookDir, 0777 & ~\umask(), true);
        }

        $template = <<<'SH'
#!/bin/sh

hookTime=$(basename "$0")
currentDir=$(dirname "$0")
alertPackageDir=$(readlink --canonicalize "$currentDir""/../../vendor/slam/run-composer-install-alert/")
sh "$alertPackageDir""/bin/check_composer_lock_change.sh" "$hookTime" "$@"

SH;

        $hooks = [self::POST_CHECKOUT_FILENAME, self::POST_MERGE_FILENAME];
        foreach ($hooks as $hook) {
            $filename = $hookDir . \DIRECTORY_SEPARATOR . $hook;
            \file_put_contents($filename, $template);
            \chmod($filename, 0755 & ~\umask());
        }
    }
}

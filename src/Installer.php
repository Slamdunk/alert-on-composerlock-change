<?php

declare(strict_types=1);

namespace Slam\AlertOnComposerlockChange;

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

from="%s"
to="%s"

git diff-tree -r --name-only --no-commit-id "$from" "$to" | grep composer.lock > /dev/null

if [ "$?" -lt 1 ]
then
    printf "%%b" "\n"
    printf "%%b" " \033[37;1;41m                                                                     \033[0m\n"
    printf "%%b" " \033[37;1;41m ! ALERT ! \033[0;1;36m composer.lock changed, run \"composer install\" \033[37;1;41m ! ALERT ! \033[0m\n"
    printf "%%b" " \033[37;1;41m                                                                     \033[0m\n"
    printf "%%b" "\n"
fi

SH;

        $hooks = [
            self::POST_CHECKOUT_FILENAME    => ['from' => '$1', 'to' => '$2'],
            self::POST_MERGE_FILENAME       => ['from' => 'ORIG_HEAD', 'to' => 'HEAD'],
        ];
        foreach ($hooks as $hook => $hookSpec) {
            $filename = $hookDir . \DIRECTORY_SEPARATOR . $hook;
            $content  = \sprintf($template, $hookSpec['from'], $hookSpec['to']);
            \file_put_contents($filename, $content);
            \chmod($filename, 0755 & ~\umask());
        }
    }
}

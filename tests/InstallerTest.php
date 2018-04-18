<?php

declare(strict_types=1);

namespace Slam\RunComposerInstallAlertTest;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use PHPUnit\Framework\TestCase;
use Slam\RunComposerInstallAlert\Installer;

/**
 * @covers \Slam\RunComposerInstallAlert\Installer
 */
final class InstallerTest extends TestCase
{
    /**
     * @var Composer|\PHPUnit_Framework_MockObject_MockObject
     */
    private $composer;

    /**
     * @var EventDispatcher|\PHPUnit_Framework_MockObject_MockObject
     */
    private $eventDispatcher;

    /**
     * @var IOInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $io;

    /**
     * @var Installer
     */
    private $installer;

    /**
     * @var string
     */
    private $assetsDir;

    /**
     * {@inheritdoc}
     *
     * @throws \PHPUnit\Framework\Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->installer       = new Installer();
        $this->io              = $this->createMock(IOInterface::class);
        $this->composer        = $this->createMock(Composer::class);
        $this->eventDispatcher = $this->getMockBuilder(EventDispatcher::class)->disableOriginalConstructor()->getMock();

        $this->composer->expects(self::any())->method('getEventDispatcher')->willReturn($this->eventDispatcher);

        $this->assetsDir = __DIR__ . \DIRECTORY_SEPARATOR . 'assets';

        $this->clearAssetsDirectory();
    }

    private function clearAssetsDirectory(): void
    {
        foreach (\glob($this->assetsDir . \DIRECTORY_SEPARATOR . 'project_*') as $dirname) {
            $this->rmDir($dirname);
        }
    }

    public function testGetSubscribedEvents(): void
    {
        $events = Installer::getSubscribedEvents();

        self::assertSame(
            [
                'post-install-cmd' => 'installGitHooks',
                'post-update-cmd'  => 'installGitHooks',
            ],
            $events
        );

        foreach ($events as $callback) {
            self::assertInternalType('callable', [$this->installer, $callback]);
        }
    }

    public function testRaiseErrorOnNonStandardVendorDir(): void
    {
        $config = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();

        $projectDir = $this->assetsDir . \DIRECTORY_SEPARATOR . \uniqid('project_');
        $vendorDir  = $projectDir . \DIRECTORY_SEPARATOR . \uniqid('subfolder_') . \DIRECTORY_SEPARATOR . \uniqid('vendor_dir_');

        $this->composer->expects(self::once())->method('getConfig')->willReturn($config);
        $config->expects(self::once())->method('get')->with('vendor-dir', Config::RELATIVE_PATHS)->willReturn($vendorDir);

        $this->expectException(\RuntimeException::class);

        Installer::installGitHooks(new Event(
            'post-install-cmd',
            $this->composer,
            $this->io
        ));
    }

    public function testRaiseErrorOnNonStandardGitProject(): void
    {
        $config = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();

        $projectDir = $this->assetsDir . \DIRECTORY_SEPARATOR . \uniqid('project_');
        $vendorDir  = $projectDir . \DIRECTORY_SEPARATOR . \uniqid('vendor_dir_');

        $this->composer->expects(self::once())->method('getConfig')->willReturn($config);
        $config->expects(self::at(0))->method('get')->with('vendor-dir', Config::RELATIVE_PATHS)->willReturn(\basename($vendorDir));
        $config->expects(self::at(1))->method('get')->with('vendor-dir')->willReturn($vendorDir);

        $this->expectException(\RuntimeException::class);

        Installer::installGitHooks(new Event(
            'post-install-cmd',
            $this->composer,
            $this->io
        ));
    }

    public function testInstallGitHooks(): void
    {
        $config = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();

        $projectDir = $this->assetsDir . \DIRECTORY_SEPARATOR . \uniqid('project_');
        $gitDir     = $projectDir . \DIRECTORY_SEPARATOR . '.git';
        \mkdir($gitDir, 0700, true);

        $vendorDir = $projectDir . \DIRECTORY_SEPARATOR . \uniqid('vendor_dir_');

        $this->composer->expects(self::once())->method('getConfig')->willReturn($config);
        $config->expects(self::at(0))->method('get')->with('vendor-dir', Config::RELATIVE_PATHS)->willReturn(\basename($vendorDir));
        $config->expects(self::at(1))->method('get')->with('vendor-dir')->willReturn($vendorDir);

        Installer::installGitHooks(new Event(
            'post-install-cmd',
            $this->composer,
            $this->io
        ));

        $expectedPostCheckoutFilename = $gitDir . \DIRECTORY_SEPARATOR . 'hooks' . \DIRECTORY_SEPARATOR . Installer::POST_CHECKOUT_FILENAME;
        $expectedPostMergeFilename    = $gitDir . \DIRECTORY_SEPARATOR . 'hooks' . \DIRECTORY_SEPARATOR . Installer::POST_MERGE_FILENAME;

        foreach ([$expectedPostCheckoutFilename, $expectedPostMergeFilename] as $file) {
            self::assertFileExists($file);
            self::assertFileIsReadable($file);
            self::assertTrue(\is_executable($file), \sprintf('%s is not executable', $file));
        }

        $this->rmDir($projectDir);
    }

    private function rmDir(string $directory): void
    {
        if (! \is_dir($directory)) {
            \unlink($directory);

            return;
        }

        \array_map(
            function ($item) use ($directory) {
                $this->rmDir($directory . '/' . $item);
            },
            \array_filter(
                \scandir($directory),
                function (string $dirItem) {
                    return ! \in_array($dirItem, ['.', '..'], true);
                }
            )
        );

        \rmdir($directory);
    }
}

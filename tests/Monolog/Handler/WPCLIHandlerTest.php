<?php

declare(strict_types=1);

/**
 * Unit tests for WPCLIHandler
 */

namespace MHCGDev\Monolog\Handler;

use MHCG\Monolog\Handler\LoggerMapEntry;
use MHCG\Monolog\Handler\WPCLIHandler;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use PHPUnit\Framework\TestCase;
use Monolog\Logger;

/**
 * Class WPCLIHandlerTest
 *
 * @covers MHCG\Monolog\Handler\WPCLIHandler
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 *
 * @package MHCGDev\Monolog\Handler
 */
class WPCLIHandlerTest extends TestCase
{
    /** @var string Constant for bodging sanity check */
    const RUNNING_IN_TEST = 'RunningInTest_RunningInTest';

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('ExitException')) {
            class_alias('MHCGDev\Monolog\Stubs\MockExitException', 'ExitException');
        }
        if (!class_exists('WP_CLI')) {
            class_alias('MHCGDev\Monolog\Stubs\MockWPCLI', 'WP_CLI');
        }
    }



    /**
     * Sanity check for all (well most at least) tests.
     *
     * Basically it's around checking if running in WP-CLI or not as unit tests should not be ran in there.
     */
    private function sanityCheck(): void
    {
        $message = 'Unit tests should not be ran from within WP-CLI environment';
        if (defined('WP_CLI')) {
            $this->assertTrue(WP_CLI == self::RUNNING_IN_TEST, $message);
        } else {
            $this->assertFalse(defined('WP_CLI'), $message);
        }
    }

    /**
     * Will need to pretend to be running under WP-CLI for most tests.
     */
    private function pretendToBeInWPCLI(): void
    {
        defined('WP_CLI') || define('WP_CLI', self::RUNNING_IN_TEST);
    }

    /**
     * Fully usable WPCLIHandler object.
     *
     * @return WPCLIHandler
     */
    private static function getHandleObjectForStandardTest(): WPCLIHandler
    {
        return new WPCLIHandler(Level::Debug);
    }

    /**
     * Fully usable Logger object.
     *
     * @return Logger
     */
    private static function getLoggerObjectForStandardTest(): Logger
    {
        return new Logger(self::RUNNING_IN_TEST);
    }

    /**
     * Partial record array with level.
     *
     * @param int $level
     * @return array
     */
    private static function getLoggerRecordArrayWithLevel(int $level = Level::Debug): array
    {
        $array = array(
            'level' => $level
        );
        return $array;
    }




    /**
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::__construct
     */
    public function testConstructorNotInWPCLI()
    {
        $this->sanityCheck();

        $this->expectException(\RuntimeException::class);
        $var = self::getHandleObjectForStandardTest();
        $this->assertTrue(is_object($var));
        unset($var);
    }

    /**
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::__construct
     */
    public function testConstructorInWPCLI()
    {
        $this->sanityCheck();

        $this->pretendToBeInWPCLI();
        $var = self::getHandleObjectForStandardTest();
        $this->assertTrue(is_object($var));
        $this->isInstanceOf('\MHCG\Monolog\Handler\WPCLIHandler');
        unset($var);
    }

    /**
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::__construct
     */
    public function testConstructorInWPCLIVerbose()
    {
        $this->sanityCheck();

        $this->pretendToBeInWPCLI();
        $var = new WPCLIHandler(Level::Debug, true, true);
        $this->assertTrue(is_object($var));
        $this->isInstanceOf('\MHCG\Monolog\Handler\WPCLIHandler');
        unset($var);
    }

    /**
     * Tests the formatter is different between standard and verbose
     *
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::getFormatter
     */
    public function testFormatterDifferent()
    {
        $this->sanityCheck();

        $this->pretendToBeInWPCLI();

        $logger = new Logger('test', [
            $handler = new TestHandler,
        ]);
        $logger->info(
            message: 'test',
            context: ['context']
        );
        $testRecord = $handler->getRecords()[0];

        $standardHandler = new WPCLIHandler(
            level: Level::Debug,
            bubble: true,
            verbose: false
        );

        $verboseHandler = new WPCLIHandler(
            level: Level::Debug,
            bubble: true,
            verbose: true
        );

        $testStandard = $standardHandler->getFormatter()->format($testRecord);
        $testVerbose = $verboseHandler->getFormatter()->format($testRecord);

        $this->assertTrue($testStandard === 'test');
        $this->assertTrue($testVerbose === 'test ["context"] []');
    }



    /**
     * Tests the default logger map contains all the Logger supported levels.
     *
     * @covers \MHCG\Monolog\Handler\WPCLIHandler->getLoggerMapEntry
     */
    public function testAllLevelsImplemented()
    {
        $builtinLevels = array_map(
            fn (string $levelName) => Logger::toMonologLevel($levelName),
            Level::NAMES
        );

        $handler = new WPCLIHandler();

        foreach ($builtinLevels as $level) {
            $this->assertNotEmpty(
                $handler->getLoggerMapEntry($level),
                message: "Level " . $level->getName() . "not implemented in WP_CLI handler"
            );
        }
    }

    /**
     * Validates the default -- ours at least should be valid right?
     *
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::validateLoggerMap
     */
    public function testValidateLoggerMapDefaultMap()
    {
        $handler = new WPCLIHandler();
        $map = $handler->getDefaultLoggerMap();
        foreach ($map as $entry) {
            $this->assertTrue($entry instanceof LoggerMapEntry);
        }
    }

    /**
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::validateLoggerMap
     */
    public function testValidateLoggerMapInvalidUseOfExit()
    {
        $this->expectExceptionMessageMatches('/is only allowed/');

        $entry = new LoggerMapEntry(
            level: Level::Debug,
            method: 'debug',
            exit: true
        );
    }

    /**
     * Tests the handler can actually be added to a Logger ok.
     */
    public function testPushHandler()
    {
        $this->sanityCheck();

        $this->pretendToBeInWPCLI();
        $logger = self::getLoggerObjectForStandardTest();
        $logger->pushHandler(self::getHandleObjectForStandardTest());

        unset($logger);
        $this->assertTrue(true);
    }

    /**
     * Test that Level::Debug() doesn't throw an error using WPCLIHander.
     *
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::write
     */
    public function testHandlerOkForDebug()
    {
        $this->sanityCheck();

        $this->pretendToBeInWPCLI();
        $logger = self::getLoggerObjectForStandardTest();
        $logger->pushHandler(self::getHandleObjectForStandardTest());

        $logger->debug('This is the end...');

        unset($logger);
        $this->assertTrue(true);
    }

    /**
     * Test that Logger::info() doesn't throw an error using WPCLIHander.
     *
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::write
     */
    public function testHandlerOkForInfo()
    {
        $this->sanityCheck();

        $this->pretendToBeInWPCLI();
        $logger = self::getLoggerObjectForStandardTest();
        $logger->pushHandler(self::getHandleObjectForStandardTest());

        $logger->info('This is the end...');

        unset($logger);
        $this->assertTrue(true);
    }

    /**
     * Test that Logger::notice() doesn't throw an error using WPCLIHander.
     *
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::write
     */
    public function testHandlerOkForNotice()
    {
        $this->sanityCheck();

        $this->pretendToBeInWPCLI();
        $logger = self::getLoggerObjectForStandardTest();
        $logger->pushHandler(self::getHandleObjectForStandardTest());

        $logger->notice('This is the end...');

        unset($logger);
        $this->assertTrue(true);
    }

    /**
     * Test that Logger::warning() doesn't throw an error using WPCLIHander.
     *
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::write
     */
    public function testHandlerOkForWarning()
    {
        $this->sanityCheck();

        $this->pretendToBeInWPCLI();
        $logger = self::getLoggerObjectForStandardTest();
        $logger->pushHandler(self::getHandleObjectForStandardTest());

        $logger->warning('This is the end...');

        unset($logger);
        $this->assertTrue(true);
    }

    /**
     * Test that Logger::error() doesn't throw an error using WPCLIHander.
     *
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::write
     */
    public function testHandlerOkForError()
    {
        $this->sanityCheck();

        $this->pretendToBeInWPCLI();
        $logger = self::getLoggerObjectForStandardTest();
        $logger->pushHandler(self::getHandleObjectForStandardTest());

        $logger->error('This is the end...');

        unset($logger);
        $this->assertTrue(true);
    }

    /**
     * Test that Logger::critical() DOES throw an error using WPCLIHander.
     *
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::write
     */
    public function disabledtestHandlerOkForCritical()
    {
        $this->sanityCheck();

        $this->pretendToBeInWPCLI();
        $logger = self::getLoggerObjectForStandardTest();
        $logger->pushHandler(self::getHandleObjectForStandardTest());

        $this->expectException('ExitException');
        $logger->critical('This is the end...');

        unset($logger);
        $this->assertTrue(true);
    }

    /**
     * Test that Logger::alert() DOES throw an error using WPCLIHander.
     *
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::write
     */
    public function disabledtestHandlerOkForAlert()
    {
        $this->sanityCheck();

        $this->pretendToBeInWPCLI();
        $logger = self::getLoggerObjectForStandardTest();
        $logger->pushHandler(self::getHandleObjectForStandardTest());

        $this->expectException('ExitException');
        $logger->alert('This is the end...');

        unset($logger);
        $this->assertTrue(true);
    }

    /**
     * Test that Logger::emergency() DOES throw an error using WPCLIHander.
     *
     * @covers \MHCG\Monolog\Handler\WPCLIHandler::write
     */
    public function disabledtestHandlerOkForEmergency()
    {
        $this->sanityCheck();

        $this->pretendToBeInWPCLI();
        $logger = self::getLoggerObjectForStandardTest();
        $logger->pushHandler(self::getHandleObjectForStandardTest());

        $this->expectException('ExitException');
        $logger->emergency('This is the end...');

        unset($logger);
        $this->assertTrue(true);
    }

}

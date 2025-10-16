<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/Env.php';

use DI\Container;
use GO\Scheduler;

$logDir = __DIR__ . '/../logs/cron';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}
$today = date('Y-m-d');
$logPath = __DIR__ . "/../logs/cron/cron-$today.log";

$lockBase = __DIR__ . '/../storage/cron-locks';
if (!is_dir($lockBase)) {
    mkdir($lockBase, 0775, true);
}

function logToFile(string $message, string $path)
{
    error_log("[" . date('Y-m-d H:i:s') . "] $message\n", 3, $path);
}

try {
    logToFile("🔁 CRON Triggered", $logPath);

    $container = new Container();
    (require __DIR__ . '/../config/Settings.php')($container);
    (require __DIR__ . '/../config/Dependencies.php')($container);
    $db = $container->get(PDO::class);

    $scheduler = new Scheduler();

    // Simple test job to verify scheduler is working
    $scheduler->call(function () use ($logPath) {
        logToFile("✅ Test job executed successfully", $logPath);
    })->everyMinute();

    // Example job - uncomment and modify when you create actual job classes
    /*
    #region Battery Low
    // BatteryLowActivityJob
    $scheduler->call(function () use ($db, $logPath) {
        logToFile("BatteryLowActivityJob started", $logPath);
        try {
            (new BatteryLowActivityJob($db))->run();
            logToFile("✅ BatteryLowActivityJob completed", $logPath);
        } catch (\Throwable $e) {
            logToFile("❌ BatteryLowActivityJob ERROR: " . $e->getMessage(), $logPath);
        }
    })->everyMinute()->onlyOne($lockBase);

    // BatteryLowFcmJob
    $scheduler->call(function () use ($db, $logPath) {
        logToFile("BatteryLowFcmJob started", $logPath);
        try {
            (new BatteryLowFcmJob($db))->run();
            logToFile("✅ BatteryLowFcmJob completed", $logPath);
        } catch (\Throwable $e) {
            logToFile("❌ BatteryLowFcmJob ERROR: " . $e->getMessage(), $logPath);
        }
    })->everyMinute()->onlyOne($lockBase);

    // BatteryLowSmsJob
    $scheduler->call(function () use ($db, $logPath) {
        logToFile("BatteryLowSmsJob started", $logPath);
        try {
            (new BatteryLowSmsJob($db))->run();
            logToFile("✅ BatteryLowSmsJob completed", $logPath);
        } catch (\Throwable $e) {
            logToFile("❌ BatteryLowSmsJob ERROR: " . $e->getMessage(), $logPath);
        }
    })->everyMinute()->onlyOne($lockBase);


    //region Email
    // EmailQueueJob
    $scheduler->call(function () use ($db, $logPath) {
        logToFile("EmailQueueJob started", $logPath);
        try {
            (new EmailQueueJob($db))->run();
            logToFile("✅ EmailQueueJob completed", $logPath);
        } catch (\Throwable $e) {
            logToFile("❌ EmailQueueJob ERROR: " . $e->getMessage(), $logPath);
        }
    })->everyMinute()->onlyOne($lockBase);
    */


    #region Extended Open Time
    // // ExtendedOpenTimeActivityJob
    // $scheduler->call(function () use ($db, $logPath) {
    //     logToFile("ExtendedOpenTimeActivityJob started", $logPath);
    //     try {
    //         (new ExtendedOpenTimeActivityJob($db))->run();
    //         logToFile("✅ ExtendedOpenTimeActivityJob completed", $logPath);
    //     } catch (\Throwable $e) {
    //         logToFile("❌ ExtendedOpenTimeActivityJob ERROR: " . $e->getMessage(), $logPath);
    //     }
    // })->everyMinute()->onlyOne($lockBase);

    // // ExtendedOpenTimeFcmJob
    // $scheduler->call(function () use ($db, $logPath) {
    //     logToFile("ExtendedOpenTimeFcmJob started", $logPath);
    //     try {
    //         (new ExtendedOpenTimeFcmJob($db))->run();
    //         logToFile("✅ ExtendedOpenTimeFcmJob completed", $logPath);
    //     } catch (\Throwable $e) {
    //         logToFile("❌ ExtendedOpenTimeFcmJob ERROR: " . $e->getMessage(), $logPath);
    //     }
    // })->everyMinute()->onlyOne($lockBase);

    // // ExtendedOpenTimeSmsJob
    // $scheduler->call(function () use ($db, $logPath) {
    //     logToFile("ExtendedOpenTimeSmsJob started", $logPath);
    //     try {
    //         (new ExtendedOpenTimeSmsJob($db))->run();
    //         logToFile("✅ ExtendedOpenTimeSmsJob completed", $logPath);
    //     } catch (\Throwable $e) {
    //         logToFile("❌ ExtendedOpenTimeSmsJob ERROR: " . $e->getMessage(), $logPath);
    //     }
    // })->everyMinute()->onlyOne($lockBase);


    /*
    #region Fcm
    // FcmQueueJob
    $scheduler->call(function () use ($db, $logPath) {
        logToFile("FcmQueueJob started", $logPath);
        try {
            (new FcmQueueJob($db))->run();
            logToFile("✅ FcmQueueJob completed", $logPath);
        } catch (\Throwable $e) {
            logToFile("❌ FcmQueueJob ERROR: " . $e->getMessage(), $logPath);
        }
    })->everyMinute()->onlyOne($lockBase);

    #region Firmware Update
    // FirmwareUpdateFcmJob
    $scheduler->call(function () use ($db, $logPath) {
        logToFile("FirmwareUpdateFcmJob started", $logPath);
        try {
            (new FirmwareUpdateFcmJob($db))->run();
            logToFile("✅ FirmwareUpdateFcmJob completed", $logPath);
        } catch (\Throwable $e) {
            logToFile("❌ FirmwareUpdateFcmJob ERROR: " . $e->getMessage(), $logPath);
        }
    })->everyMinute()->onlyOne($lockBase);


    #region Offline
    // OfflineNormalModeActivityJob
    $scheduler->call(function () use ($db, $logPath) {
        logToFile("OfflineNormalModeActivityJob started", $logPath);
        try {
            (new OfflineNormalModeActivityJob($db))->run();
            logToFile("✅ OfflineNormalModeActivityJob completed", $logPath);
        } catch (\Throwable $e) {
            logToFile("❌ OfflineNormalModeActivityJob ERROR: " . $e->getMessage(), $logPath);
        }
    })->everyMinute()->onlyOne($lockBase);

    // OfflineNormalModeFcmJob
    $scheduler->call(function () use ($db, $logPath) {
        logToFile("OfflineNormalModeFcmJob started", $logPath);
        try {
            (new OfflineNormalModeFcmJob($db))->run();
            logToFile("✅ OfflineNormalModeFcmJob completed", $logPath);
        } catch (\Throwable $e) {
            logToFile("❌ OfflineNormalModeFcmJob ERROR: " . $e->getMessage(), $logPath);
        }
    })->everyMinute()->onlyOne($lockBase);

    // OfflineSecureModeActivityJob
    $scheduler->call(function () use ($db, $logPath) {
        logToFile("OfflineSecureModeActivityJob started", $logPath);
        try {
            (new OfflineSecureModeActivityJob($db))->run();
            logToFile("✅ OfflineSecureModeActivityJob completed", $logPath);
        } catch (\Throwable $e) {
            logToFile("❌ OfflineSecureModeActivityJob ERROR: " . $e->getMessage(), $logPath);
        }
    })->everyMinute()->onlyOne($lockBase);

    // OfflineSecureModeFcmJob
    $scheduler->call(function () use ($db, $logPath) {
        logToFile("OfflineSecureModeFcmJob started", $logPath);
        try {
            (new OfflineSecureModeFcmJob($db))->run();
            logToFile("✅ OfflineSecureModeFcmJob completed", $logPath);
        } catch (\Throwable $e) {
            logToFile("❌ OfflineSecureModeFcmJob ERROR: " . $e->getMessage(), $logPath);
        }
    })->everyMinute()->onlyOne($lockBase);
    */

    // Run scheduler
    $scheduler->run();

    logToFile("🔁 CRON Closed", $logPath);
} catch (\Throwable $e) {
    logToFile("❌ CRON ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString(), $logPath);
}

<?php

    /** @noinspection DuplicatedCode */


    /**
     * cli.php is the main execution point for the bot to start polling, this method uses BackgroundWorker to
     * instantly process a batch of updates in the background without waiting for the updates to be completed.
     *
     * In exchange for this performance upgrade, each worker will use up database connections, make sure
     * the database can handle these connections without maxing out
     */

    use BackgroundWorker\BackgroundWorker;
    use Longman\TelegramBot\Exception\TelegramException;
    use ppm\ppm;
    use VerboseAdventure\Abstracts\EventType;
    use VerboseAdventure\VerboseAdventure;


/** @noinspection PhpIncludeInspection */
    require("ppm");

    // Import all required auto loaders

    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.acm");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.background_worker");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.coffeehouse");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.deepanalytics");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.telegram_client_manager");
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.verbose_adventure");

    VerboseAdventure::setStdout(true); // Enable stdout
    \VerboseAdventure\Classes\ErrorHandler::registerHandlers(); // Register error handlers

    $current_directory = getcwd();

    if(file_exists($current_directory . DIRECTORY_SEPARATOR . "vendor") == false)
    {
        $current_directory = __DIR__;
    }

    if(file_exists($current_directory . DIRECTORY_SEPARATOR . "vendor") == false)
    {
        print("Cannot find vendor directory" . PHP_EOL);
        exit(255);
    }

    require($current_directory . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');

    if(class_exists("LydiaTelegramBot") == false)
    {
        include_once($current_directory . DIRECTORY_SEPARATOR . 'LydiaTelegramBot.php');
    }

    // Load all configurations
    /** @noinspection PhpUnhandledExceptionInspection */
    $TelegramServiceConfiguration = LydiaTelegramBot::getTelegramConfiguration();

    /** @noinspection PhpUnhandledExceptionInspection */
    $DatabaseConfiguration = LydiaTelegramBot::getDatabaseConfiguration();

    /** @noinspection PhpUnhandledExceptionInspection */
    $BackgroundWorkerConfiguration = LydiaTelegramBot::getBackgroundWorkerConfiguration();

    // Create the Telegram Bot instance (NO SQL)

    define("TELEGRAM_BOT_NAME", $TelegramServiceConfiguration['BotName']);
    LydiaTelegramBot::setLogHandler(new VerboseAdventure(TELEGRAM_BOT_NAME));

    if(strtolower($TelegramServiceConfiguration['BotName']) == 'true')
    {
        define("TELEGRAM_BOT_ENABLED", true);
    }
    else
    {
        define("TELEGRAM_BOT_ENABLED", false);
    }

    LydiaTelegramBot::getLogHandler()->log(EventType::INFO, "Starting Service", "Main");
    
    try
    {
        $telegram = new Longman\TelegramBot\Telegram(
            $TelegramServiceConfiguration['BotToken'],
            $TelegramServiceConfiguration['BotName']
        );
    }
    catch (Longman\TelegramBot\Exception\TelegramException $e)
    {
        LydiaTelegramBot::getLogHandler()->logException($e, "Main");
        exit(255);
    }

    $telegram->useGetUpdatesWithoutDatabase();

    // Start the workers using the supervisor
    LydiaTelegramBot::getLogHandler()->log(EventType::INFO, "Starting Supervisor", "Main");

    try
    {
        LydiaTelegramBot::$BackgroundWorker = new BackgroundWorker();
        LydiaTelegramBot::getBackgroundWorker()->getClient()->addServer(
            $BackgroundWorkerConfiguration["Host"],
            (int)$BackgroundWorkerConfiguration["Port"]
        );
        LydiaTelegramBot::getBackgroundWorker()->getSupervisor()->restartWorkers(
            $current_directory . DIRECTORY_SEPARATOR . 'worker.php', TELEGRAM_BOT_NAME,
            (int)$BackgroundWorkerConfiguration['MaxWorkers']
        );
    }
    catch(Exception $e)
    {
        LydiaTelegramBot::getLogHandler()->logException($e, "Main");
        exit(255);
    }

    // Start listening to updates
    while(true)
    {
        try
        {
            LydiaTelegramBot::getLogHandler()->log(EventType::INFO, "Listening for updates", "Main");
            $server_response = $telegram->handleBackgroundUpdates(LydiaTelegramBot::getBackgroundWorker());
            if ($server_response->isOk())
            {
                $update_count = count($server_response->getResult());
                if($update_count > 0)
                {
                    LydiaTelegramBot::getLogHandler()->log(EventType::INFO, "Processed $update_count update(s)", "Main");
                }
            }
            else
            {
                LydiaTelegramBot::getLogHandler()->log(EventType::ERROR, "Failed to fetch updates: " . $server_response->printError(true), "Main");
            }
        }
        catch (TelegramException $e)
        {
            LydiaTelegramBot::getLogHandler()->logException($e, "Main");
        }
    }
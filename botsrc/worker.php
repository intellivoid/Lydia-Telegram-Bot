<?php

    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection DuplicatedCode */

    /**
     * worker.php is the code that the worker will execute whenever a job passed on from the main
     * bot. Starting the CLI will restart the workers that are already running in the background
     */

    use BackgroundWorker\BackgroundWorker;
    use CoffeeHouse\CoffeeHouse;
    use DeepAnalytics\DeepAnalytics;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Entities\Update;
    use ppm\ppm;
    use TelegramClientManager\TelegramClientManager;
    use VerboseAdventure\Abstracts\EventType;
    use VerboseAdventure\VerboseAdventure;
    use VerboseAdventure\Classes\ErrorHandler;

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
    /** @noinspection PhpUnhandledExceptionInspection */
    ppm::import("net.intellivoid.tdlib");

    VerboseAdventure::setStdout(true); // Enable stdout
    ErrorHandler::registerHandlers(); // Register error handlers

    $current_directory = getcwd();

    if(class_exists("LydiaTelegramBot") == false)
    {
        if(file_exists($current_directory . DIRECTORY_SEPARATOR . 'LydiaTelegramBot.php'))
        {
            require_once($current_directory . DIRECTORY_SEPARATOR . 'LydiaTelegramBot.php');
        }
        elseif(file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'LydiaTelegramBot.php'))
        {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . 'LydiaTelegramBot.php');
        }
        else
        {
            throw new RuntimeException("Cannot locate bot class");
        }
    }
    // Load all required configurations

    /** @noinspection PhpUnhandledExceptionInspection */
    $TelegramServiceConfiguration = LydiaTelegramBot::getTelegramConfiguration();

    /** @noinspection PhpUnhandledExceptionInspection */
    $DatabaseConfiguration = LydiaTelegramBot::getDatabaseConfiguration();

    /** @noinspection PhpUnhandledExceptionInspection */
    $BackgroundWorkerConfiguration = LydiaTelegramBot::getBackgroundWorkerConfiguration();

    // Define and create the Telegram Bot instance (SQL)

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

    try
    {
        $telegram = new Longman\TelegramBot\Telegram(
            $TelegramServiceConfiguration['BotToken'],
            $TelegramServiceConfiguration['BotName']
        );

        if(file_exists($current_directory . DIRECTORY_SEPARATOR . 'LydiaTelegramBot.php'))
        {
            $telegram->addCommandsPaths([$current_directory . DIRECTORY_SEPARATOR . 'commands']);
        }
        elseif(file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'LydiaTelegramBot.php'))
        {
            $telegram->addCommandsPaths([__DIR__ . DIRECTORY_SEPARATOR . 'commands']);
        }
        else
        {
            print("Cannot locate commands path");
            exit(1);
        }

        \Longman\TelegramBot\TelegramLog::initialize();
    }
    catch (Longman\TelegramBot\Exception\TelegramException $e)
    {
        LydiaTelegramBot::getLogHandler()->logException($e, "Worker");
        exit(255);
    }

    try
    {
        $telegram->enableMySql(array(
            'host' => $DatabaseConfiguration['Host'],
            'port' => $DatabaseConfiguration['Port'],
            'user' => $DatabaseConfiguration['Username'],
            'password' => $DatabaseConfiguration['Password'],
            'database' => $DatabaseConfiguration['Database'],
        ));
    }
    catch(Exception $e)
    {
        LydiaTelegramBot::getLogHandler()->logException($e, "Worker");
        exit(255);
    }

    // Start the worker instance
    LydiaTelegramBot::getLogHandler()->log(EventType::INFO, "Starting worker", "Worker");
    LydiaTelegramBot::$DeepAnalytics = new DeepAnalytics();

    // Create the database connections
    LydiaTelegramBot::$TelegramClientManager = new TelegramClientManager();
    if(LydiaTelegramBot::$TelegramClientManager->getDatabase()->connect_error)
    {
        LydiaTelegramBot::getLogHandler()->log(EventType::ERROR, "Failed to initialize TelegramClientManager", "Worker");
        LydiaTelegramBot::getLogHandler()->log(EventType::ERROR, LydiaTelegramBot::$TelegramClientManager->getDatabase()->connect_error, "Worker");
        exit(255);
    }
    
    LydiaTelegramBot::$CoffeeHouse = new CoffeeHouse();
    if(LydiaTelegramBot::$CoffeeHouse->getDatabase()->connect_error)
    {
        LydiaTelegramBot::getLogHandler()->log(EventType::ERROR, "Failed to initialize CoffeeHouse", "Worker");
        LydiaTelegramBot::getLogHandler()->log(EventType::ERROR, LydiaTelegramBot::$CoffeeHouse->getDatabase()->connect_error, "Worker");
        exit(255);
    }

    try
    {
        $BackgroundWorker = new BackgroundWorker();
        $BackgroundWorker->getWorker()->addServer(
            $BackgroundWorkerConfiguration["Host"],
            (int)$BackgroundWorkerConfiguration["Port"]
        );
    }
    catch(Exception $e)
    {
        LydiaTelegramBot::getLogHandler()->logException($e, "Worker");
        LydiaTelegramBot::getLogHandler()->log(EventType::WARNING, "Make sure Gearman is running!", "Worker");
        exit(255);
    }

    // Define the function "process_batch" to process a batch of Updates from Telegram in the background
    $BackgroundWorker->getWorker()->getGearmanWorker()->addFunction($telegram->getBotUsername() . "_updates", function(GearmanJob $job) use ($telegram)
    {
        try
        {
            $ServerResponse = new ServerResponse(json_decode($job->workload(), true), TELEGRAM_BOT_NAME);

            if(is_null($ServerResponse->getResult()) == false)
            {
                $UpdateCount = count($ServerResponse->getResult());

                if($UpdateCount > 0)
                {
                    LydiaTelegramBot::getLogHandler()->log(EventType::INFO, "Processing $UpdateCount update(s)", "Worker");

                    /** @var Update $result */
                    foreach ($ServerResponse->getResult() as $result)
                    {
                        try
                        {
                            LydiaTelegramBot::getLogHandler()->log(EventType::INFO, "Processing update ID " . $result->getUpdateId(), "Worker");
                            $telegram->processUpdate($result);
                        }
                        catch(Exception $e)
                        {
                            LydiaTelegramBot::getLogHandler()->logException($e, "Worker");
                        }
                    }
                }
            }
        }
        catch(Exception $e)
        {
            LydiaTelegramBot::getLogHandler()->logException($e, "Worker");
        }

    });

    // Start working
    LydiaTelegramBot::getLogHandler()->log(EventType::INFO, "Worker initialized", "Worker");

    while(true)
    {
        //try
        //{
            //try
            //{
            //    DB::getPdo()->query('SELECT 1');
            //}
            //catch (PDOException $e)
            //{
            //    $telegram->enableMySql(array(
            //        'host' => $DatabaseConfiguration['Host'],
            //        'port' => $DatabaseConfiguration['Port'],
            //        'user' => $DatabaseConfiguration['Username'],
            //        'password' => $DatabaseConfiguration['Password'],
            //        'database' => $DatabaseConfiguration['Database'],
            //    ));
            //}

            //LydiaTelegramBot::$CoffeeHouse->getDatabase()->ping();
            //LydiaTelegramBot::$TelegramClientManager->getDatabase()->ping();
        //}
        //catch(Exception $e)
        //{
        //    LydiaTelegramBot::getLogHandler()->logException($e, "Worker");
        //}

        try
        {
            $BackgroundWorker->getWorker()->work();
        }
        catch(Exception $e)
        {
            LydiaTelegramBot::getLogHandler()->logException($e, "Worker");
        }
    }

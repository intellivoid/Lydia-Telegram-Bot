<?php

    /** @noinspection PhpUndefinedClassInspection */

    use acm\acm;
    use acm\Objects\Schema;
    use BackgroundWorker\BackgroundWorker;
    use CoffeeHouse\CoffeeHouse;
    use DeepAnalytics\DeepAnalytics;
    use TelegramClientManager\TelegramClientManager;
use VerboseAdventure\VerboseAdventure;

/**
     * Class LydiaTelegramBot
     */
    class LydiaTelegramBot
    {

        /**
         * @var BackgroundWorker
         */
        public static $BackgroundWorker;

        /**
         * @var CoffeeHouse
         */
        public static $CoffeeHouse;

        /**
         * @var DeepAnalytics
         * @noinspection PhpUndefinedClassInspection
         */
        public static $DeepAnalytics;

        /**
         * @var TelegramClientManager
         */
        public static $TelegramClientManager;

        /**
         * @var VerboseAdventure
         */
        public static $LogHandler;

        /**
         * Auto Configures ACM
         *
         * @return acm
         * @noinspection DuplicatedCode
         */
        public static function autoConfig(): acm
        {
            $acm = new acm(__DIR__, 'Lydia Telegram Bot');

            $TelegramSchema = new Schema();
            $TelegramSchema->setDefinition('BotName', '<BOT NAME HERE>');
            $TelegramSchema->setDefinition('BotToken', '<BOT TOKEN>');
            $TelegramSchema->setDefinition('BotEnabled', 'true');
            $TelegramSchema->setDefinition('WebHook', 'http://localhost');
            $TelegramSchema->setDefinition('MaxConnections', 100);
            $acm->defineSchema('TelegramService', $TelegramSchema);

            $BackgroundWorkerSchema = new Schema();
            $BackgroundWorkerSchema->setDefinition('Host', '127.0.0.1');
            $BackgroundWorkerSchema->setDefinition('Port', '4730');
            $BackgroundWorkerSchema->setDefinition('MaxWorkers', '30');
            $acm->defineSchema('BackgroundWorker', $BackgroundWorkerSchema);

            $DatabaseSchema = new Schema();
            $DatabaseSchema->setDefinition('Host', '127.0.0.1');
            $DatabaseSchema->setDefinition('Port', '3306');
            $DatabaseSchema->setDefinition('Username', 'root');
            $DatabaseSchema->setDefinition('Password', 'admin');
            $DatabaseSchema->setDefinition('Database', 'telegram');
            $acm->defineSchema('Database', $DatabaseSchema);

            return $acm;
        }

        /**
         * Returns the Telegram Service configuration
         *
         * @return mixed
         * @throws Exception
         */
        public static function getTelegramConfiguration()
        {
            return self::autoConfig()->getConfiguration('TelegramService');
        }

        /**
         * Returns the database configuration
         *
         * @return mixed
         * @throws Exception
         */
        public static function getDatabaseConfiguration()
        {
            return self::autoConfig()->getConfiguration('Database');
        }

        /**
         * Returns the background worker configuration
         *
         * @return mixed
         * @throws Exception
         */
        public static function getBackgroundWorkerConfiguration()
        {
            return self::autoConfig()->getConfiguration('BackgroundWorker');
        }

        /**
         * @return TelegramClientManager
         */
        public static function getTelegramClientManager(): TelegramClientManager
        {
            return self::$TelegramClientManager;
        }

        /**
         * @return BackgroundWorker
         */
        public static function getBackgroundWorker(): BackgroundWorker
        {
            return self::$BackgroundWorker;
        }

        /**
         * @return DeepAnalytics
         */
        public static function getDeepAnalytics(): DeepAnalytics
        {
            return self::$DeepAnalytics;
        }

        /**
         * @return CoffeeHouse
         */
        public static function getCoffeeHouse(): CoffeeHouse
        {
            return self::$CoffeeHouse;
        }

        /**
         * @return VerboseAdventure
         */
        public static function getLogHandler(): VerboseAdventure
        {
            return self::$LogHandler;
        }

        /**
         * @param VerboseAdventure $LogHandler
         */
        public static function setLogHandler(VerboseAdventure $LogHandler): void
        {
            self::$LogHandler = $LogHandler;
        }
    }
<?php

    use acm\acm;
    use acm\Objects\Schema;
    use CoffeeHouse\CoffeeHouse;

    require __DIR__ . '/vendor/autoload.php';
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'CoffeeHouse' . DIRECTORY_SEPARATOR . 'CoffeeHouse.php');
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'TelegramClientManager' . DIRECTORY_SEPARATOR . 'TelegramClientManager.php');

    $acm = new acm(__DIR__, 'Lydia Telegram Bot');

    $TelegramSchema = new Schema();
    $TelegramSchema->setDefinition('BotName', '<BOT NAME HERE>');
    $TelegramSchema->setDefinition('BotToken', '<BOT TOKEN>');
    $TelegramSchema->setDefinition('BotEnabled', 'true');
    $TelegramSchema->setDefinition('WebHook', 'http://localhost');
    $acm->defineSchema('TelegramService', $TelegramSchema);

    $TelegramServiceConfiguration = $acm->getConfiguration('TelegramService');
    define("TELEGRAM_BOT_NAME", $TelegramServiceConfiguration['BotName'], false);

    $CoffeeHouse = new CoffeeHouse();
    $telegram = new Longman\TelegramBot\Telegram(
        $TelegramServiceConfiguration['BotToken'],
        $TelegramServiceConfiguration['BotName']
    );
    $telegram->addCommandsPaths([__DIR__ . DIRECTORY_SEPARATOR . 'commands']);
    $telegram->handle();
<?php

    use acm\acm;
    use acm\Objects\Schema;
    use Longman\TelegramBot\Exception\TelegramException;

    require __DIR__ . '/vendor/autoload.php';
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'CoffeeHouse' . DIRECTORY_SEPARATOR . 'CoffeeHouse.php');

    $acm = new acm(__DIR__, 'Lydia Telegram Bot');

    $TelegramSchema = new Schema();
    $TelegramSchema->setDefinition('BotName', '<BOT NAME HERE>');
    $TelegramSchema->setDefinition('BotToken', '<BOT TOKEN>');
    $TelegramSchema->setDefinition('BotEnabled', 'true');
    $TelegramSchema->setDefinition('WebHook', 'http://localhost');
    $acm->defineSchema('TelegramService', $TelegramSchema);

    $TelegramServiceConfiguration = $acm->getConfiguration('TelegramService');

    try
    {
        $telegram = new Longman\TelegramBot\Telegram(
            $TelegramServiceConfiguration['BotToken'],
            $TelegramServiceConfiguration['BotName']
        );
    }
    catch (TelegramException $e)
    {
        print('\Longman\TelegramBot\Exception\TelegramException' . "\n\n");
        print($e->getMessage());
        exit(255);
    }

    try
    {
        $result = $telegram->setWebhook($TelegramServiceConfiguration['WebHook']);
    }
    catch (TelegramException $e)
    {
        print('\Longman\TelegramBot\Exception\TelegramException' . "\n\n");
        print($e->getMessage());
        exit(255);
    }

    exit(0);
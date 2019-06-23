<?php

    use CoffeeHouse\CoffeeHouse;
    use Longman\TelegramBot\Exception\TelegramException;

    require __DIR__ . '/vendor/autoload.php';
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'CoffeeHouse' . DIRECTORY_SEPARATOR . 'CoffeeHouse.php');

    $CoffeeHouse = new CoffeeHouse();

    try
    {
        $telegram = new Longman\TelegramBot\Telegram(
            $CoffeeHouse->getTelegramConfiguration()['ApiKey'],
            $CoffeeHouse->getTelegramConfiguration()['BotName']
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
        $result = $telegram->setWebhook($CoffeeHouse->getTelegramConfiguration()['WebHook']);
    }
    catch (TelegramException $e)
    {
        print('\Longman\TelegramBot\Exception\TelegramException' . "\n\n");
        print($e->getMessage());
        exit(255);
    }

    exit(0);
<?php

    use CoffeeHouse\CoffeeHouse;

    require __DIR__ . '/vendor/autoload.php';
    include_once(__DIR__ . DIRECTORY_SEPARATOR . 'CoffeeHouse' . DIRECTORY_SEPARATOR . 'CoffeeHouse.php');

    $CoffeeHouse = new CoffeeHouse();
    $telegram = new Longman\TelegramBot\Telegram(
        $CoffeeHouse->getTelegramConfiguration()['ApiKey'],
        $CoffeeHouse->getTelegramConfiguration()['BotName']
    );
    $telegram->addCommandsPaths([__DIR__ . DIRECTORY_SEPARATOR,]);
    $telegram->handle();
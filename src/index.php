<?php

    use acm\acm;
    use acm\Objects\Schema;
    use CoffeeHouse\CoffeeHouse;
    use Longman\TelegramBot\Exception\TelegramException;

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

    try
    {
        $telegram->handle();
    }
    catch (TelegramException $e)
    {
        ?>
        <h1>Access Denied</h1>
        <p>Nothing to see here, the current time is <?PHP print(hash('sha256', time() . 'IV')); ?></p>
        <?php
    }
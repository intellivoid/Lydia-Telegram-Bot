<?php

    namespace Longman\TelegramBot\Commands\SystemCommands;
    use CoffeeHouse\Bots\Cleverbot;
    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Exceptions\BotSessionException;
    use CoffeeHouse\Exceptions\DatabaseException;
    use CoffeeHouse\Exceptions\ForeignSessionNotFoundException;
    use CoffeeHouse\Exceptions\InvalidSearchMethodException;
    use CoffeeHouse\Exceptions\TelegramClientNotFoundException;
    use Longman\TelegramBot\ChatAction;
    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use Longman\TelegramBot\Telegram;

    /**
     * Start command
     *
     * Gets executed when a user first starts using the bot.
     */
    class GenericmessageCommand extends SystemCommand
    {

        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * Command execute method
         *
         * @return ServerResponse
         * @throws DatabaseException
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws BotSessionException
         * @throws ForeignSessionNotFoundException
         * @throws InvalidSearchMethodException
         */
        public function execute()
        {
            $message = $this->getMessage();

            $CoffeeHouse = new CoffeeHouse();
            $TelegramClient = $CoffeeHouse->getTelegramClientManager()->syncClient($message->getChat()->getId());

            if($message->getChat()->isGroupChat())
            {
                if($message->getReplyToMessage() !== null)
                {
                    if($message->getReplyToMessage()->getBotUsername() !== $CoffeeHouse->getTelegramConfiguration()['BotName'])
                    {
                        return null;
                    }
                }
                elseif(!stripos(strtolower($message->getText(true)), 'lydia'))
                {
                    return null;
                }
            }

            if($message->getChat()->isSuperGroup())
            {
                if($message->getReplyToMessage() !== null)
                {
                    if($message->getReplyToMessage()->getBotUsername() !== $CoffeeHouse->getTelegramConfiguration()['BotName'])
                    {
                        return null;
                    }
                }
                elseif(!stripos(strtolower($message->getText(true)), 'lydia'))
                {
                    return null;
                }
            }

            $Bot = new Cleverbot($CoffeeHouse);

            Request::sendChatAction([
                'chat_id' => $message->getChat()->getId(),
                'action' => ChatAction::TYPING
            ]);

            // Check if the Telegram Client has a session ID
            if($TelegramClient->ForeignSessionID == 'None')
            {
                $Bot->newSession('en');
                $TelegramClient->ForeignSessionID = $Bot->getSession()->SessionID;
                $CoffeeHouse->getTelegramClientManager()->updateClient($TelegramClient);
            }
            else
            {
                $Bot->loadSession($TelegramClient->ForeignSessionID);
                if(time() > $Bot->getSession()->Expires)
                {
                    $Bot->newSession('en');
                    $TelegramClient->ForeignSessionID = $Bot->getSession()->SessionID;
                    $CoffeeHouse->getTelegramClientManager()->updateClient($TelegramClient);
                }
            }


            $Output = $Bot->think($message->getText(true));

            $data = [
                'chat_id' => $message->getChat()->getId(),
                'text' => $Output . "\n\n"
            ];

            return Request::sendMessage($data);

        }
    }
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
    use ModularAPI\Abstracts\AccessKeySearchMethod;
    use ModularAPI\ModularAPI;

    /**
     * Start command
     *
     * Gets executed when a user first starts using the bot.
     */
    class ChatCommand extends SystemCommand
    {
        /**
         * @var string
         */
        protected $name = 'chat';

        /**
         * @var string
         */
        protected $description = 'Send a chat message to the bot';

        /**
         * @var string
         */
        protected $usage = '/chat';

        /**
         * @var string
         */
        protected $version = '1.0.0';

        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * Command execute method
         *
         * @return ServerResponse
         * @throws BotSessionException
         * @throws DatabaseException
         * @throws ForeignSessionNotFoundException
         * @throws InvalidSearchMethodException
         * @throws TelegramClientNotFoundException
         * @throws TelegramException
         * @throws \ModularAPI\Exceptions\AccessKeyExpiredException
         * @throws \ModularAPI\Exceptions\AccessKeyNotFoundException
         * @throws \ModularAPI\Exceptions\NoResultsFoundException
         * @throws \ModularAPI\Exceptions\UnsupportedSearchMethodException
         * @throws \ModularAPI\Exceptions\UsageExceededException
         */
        public function execute()
        {
            $message = $this->getMessage();

            $CoffeeHouse = new CoffeeHouse();
            $TelegramClient = $CoffeeHouse->getTelegramClientManager()->syncClient($message->getChat()->getId());

            if(strlen($message->getText(true)) == 0)
            {
                $data = [
                    'chat_id' => $message->getChat()->getId(),
                    'text' => "Learn to use the chat command thx"
                ];

                return Request::sendMessage($data);
            }

            Request::sendChatAction([
                'chat_id' => $message->getChat()->getId(),
                'action' => ChatAction::TYPING
            ]);

            $Bot = new Cleverbot($CoffeeHouse);

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

            try
            {
                $Output = $Bot->think($message->getText(true));
            }
            catch(BotSessionException $botSessionException)
            {
                // Mark is unavailable
                $Bot->getSession()->Available = false;
                $CoffeeHouse->getForeignSessionsManager()->updateSession($Bot->getSession());

                $Bot->newSession('en');
                $TelegramClient->ForeignSessionID = $Bot->getSession()->SessionID;
                $CoffeeHouse->getTelegramClientManager()->updateClient($TelegramClient);

                // Rethink the output
                $Output = $Bot->think($message->getText(true));
            }

            $ModularAPI = new ModularAPI();
            $AccessKey = $ModularAPI->AccessKeys()->Manager->get(
                AccessKeySearchMethod::byPublicID,
                '0067db960e18a3c30cb109df2d66dab601e78601cff1404410e4e7c58f0f199b'
            );
            $ModularAPI->AccessKeys()->trackUsage($AccessKey, false);

            $data = [
                'chat_id' => $message->getChat()->getId(),
                'text' => $Output
            ];

            return Request::sendMessage($data);

        }
    }
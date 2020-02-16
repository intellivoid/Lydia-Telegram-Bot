<?php

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use CoffeeHouse\Bots\Cleverbot;
    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Exceptions\BotSessionException;
    use CoffeeHouse\Exceptions\DatabaseException;
    use CoffeeHouse\Exceptions\ForeignSessionNotFoundException;
    use CoffeeHouse\Exceptions\InvalidSearchMethodException;
    use Exception;
    use Longman\TelegramBot\ChatAction;
    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;
    use TelegramClientManager\TelegramClientManager;

    /**
     * Chat command
     *
     * Gets executed when a user sends '/chat'
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
         * Executes the chat command
         *
         * @return ServerResponse
         * @throws BotSessionException
         * @throws DatabaseException
         * @throws ForeignSessionNotFoundException
         * @throws InvalidSearchMethodException
         * @throws TelegramException
         * @throws \TelegramClientManager\Exceptions\DatabaseException
         */
        public function execute()
        {
            $TelegramClientManager = new TelegramClientManager();

            try
            {
                $TelegramClient = $TelegramClientManager->getTelegramClientManager()->registerClient(
                    Chat::fromArray($this->getMessage()->getChat()->getRawData()),
                    User::fromArray($this->getMessage()->getFrom()->getRawData())
                );
            }
            catch(Exception $e)
            {
                $data = [
                    'chat_id' => $this->getMessage()->getChat()->getId(),
                    'reply_to_message_id' => $this->getMessage()->getMessageId(),
                    'text' => "Oops! Something went wrong! contact someone in @IntellivoidDev"
                ];

                return Request::sendMessage($data);
            }

            $CoffeeHouse = new CoffeeHouse();

            if(strlen($this->getMessage()->getText(true)) == 0)
            {
                $data = [
                    'chat_id' => $this->getMessage()->getChat()->getId(),
                    'reply_to_message_id' => $this->getMessage()->getMessageId(),
                    'text' => "Learn to use the chat command thx"
                ];

                return Request::sendMessage($data);
            }

            Request::sendChatAction([
                'chat_id' => $this->getMessage()->getChat()->getId(),
                'action' => ChatAction::TYPING
            ]);

            $Bot = new Cleverbot($CoffeeHouse);

            if(isset($TelegramClient->SessionData->Data['lydia_default_language']) == false)
            {
                if(is_null($this->getMessage()->getFrom()->getLanguageCode()))
                {
                    $TelegramClient->SessionData->Data['lydia_default_language'] = 'en';
                }
                else
                {
                    $TelegramClient->SessionData->Data['lydia_default_language'] = $this->getMessage()->getFrom()->getLanguageCode();
                }
                $TelegramClientManager->getTelegramClientManager()->updateClient($TelegramClient);
            }

            // Check if the Telegram Client has a session ID
            if(isset($TelegramClient->SessionData->Data['lydia_session_id']) == false)
            {
                $Bot->newSession($TelegramClient->SessionData->Data['lydia_default_language']);
                $TelegramClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                $TelegramClientManager->getTelegramClientManager()->updateClient($TelegramClient);

            }
            else
            {
                $Bot->loadSession($TelegramClient->SessionData->Data['lydia_session_id']);
                if((int)time() > $Bot->getSession()->Expires)
                {
                    $Bot->newSession($TelegramClient->SessionData->Data['lydia_default_language']);
                    $TelegramClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                    $TelegramClientManager->getTelegramClientManager()->updateClient($TelegramClient);
                }
            }

            try
            {
                $Output = $Bot->think($this->getMessage()->getText(true));
            }
            catch(BotSessionException $botSessionException)
            {
                // Mark is unavailable
                $Bot->getSession()->Available = false;
                $CoffeeHouse->getForeignSessionsManager()->updateSession($Bot->getSession());

                $Bot->newSession($TelegramClient->SessionData->Data['lydia_default_language']);
                $TelegramClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                $TelegramClientManager->getTelegramClientManager()->updateClient($TelegramClient);

                // Rethink the output
                $Output = $Bot->think($this->getMessage()->getText(true));
            }

            $data = [
                'chat_id' => $this->getMessage()->getChat()->getId(),
                'reply_to_message_id' => $this->getMessage()->getMessageId(),
                'text' => $Output
            ];

            return Request::sendMessage($data);

        }
    }
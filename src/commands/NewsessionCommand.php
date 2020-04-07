<?php

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use CoffeeHouse\Bots\Cleverbot;
    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Exceptions\BotSessionException;
    use CoffeeHouse\Exceptions\ForeignSessionNotFoundException;
    use CoffeeHouse\Exceptions\InvalidSearchMethodException;
    use DeepAnalytics\DeepAnalytics;
    use Exception;
    use Longman\TelegramBot\ChatAction;
    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;
    use TelegramClientManager\TelegramClientManager;

    /**
     * Newsession Command
     *
     * Gets executed when a user sends /newsession
     */
    class NewsessionCommand extends SystemCommand
    {
        /**
         * @var string
         */
        protected $name = 'newsession';

        /**
         * @var string
         */
        protected $description = 'Creates a new session if the the session is older than 60 seconds';

        /**
         * @var string
         */
        protected $usage = '/newsession';

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
         * @throws TelegramException
         *\ @throws BotSessionException
         * @throws \CoffeeHouse\Exceptions\DatabaseException
         * @throws ForeignSessionNotFoundException
         * @throws InvalidSearchMethodException
         * @throws DatabaseException
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


            Request::sendChatAction([
                'chat_id' => $this->getMessage()->getChat()->getId(),
                'action' => ChatAction::TYPING
            ]);

            $CoffeeHouse = new CoffeeHouse();
            $DeepAnalytics = new DeepAnalytics();
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

                $DeepAnalytics->tally('tg_lydia', 'created_sessions', 0);
                $DeepAnalytics->tally('tg_lydia', 'created_sessions', (int)$TelegramClient->getChatId());
            }
            else
            {
                $Bot->loadSession($TelegramClient->SessionData->Data['lydia_session_id']);
                $MissCalculation = abs(($Bot->getSession()->Expires - time())  - 10800);
                if($MissCalculation > 60)
                {
                    $Bot->newSession($TelegramClient->SessionData->Data['lydia_default_language']);
                    $TelegramClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                    $TelegramClientManager->getTelegramClientManager()->updateClient($TelegramClient);

                    $DeepAnalytics->tally('tg_lydia', 'created_sessions', 0);
                    $DeepAnalytics->tally('tg_lydia', 'created_sessions', (int)$TelegramClient->getChatId());
                }
                else
                {
                    $data = [
                        'chat_id' => $this->getMessage()->getChat()->getId(),
                        'reply_to_message_id' => $this->getMessage()->getMessageId(),
                        'text' => "The session must be older than 60 seconds"
                    ];

                    return Request::sendMessage($data);
                }
            }

            $data = [
                'chat_id' => $this->getMessage()->getChat()->getId(),
                'reply_to_message_id' => $this->getMessage()->getMessageId(),
                'text' => "A new session has been successfully created"
            ];

            return Request::sendMessage($data);
        }
    }
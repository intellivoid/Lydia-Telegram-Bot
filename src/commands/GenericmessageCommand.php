<?php

    /** @noinspection PhpUndefinedClassInspection */

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
     * Generic Command
     *
     * Gets executed when a user sends a generic message
     */
    class GenericmessageCommand extends SystemCommand
    {

        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * @var string
         */
        protected $version = '1.0.1';

        /**
         * Executes the generic message command
         *
         * @return ServerResponse|null
         * @throws BotSessionException
         * @throws \CoffeeHouse\Exceptions\DatabaseException
         * @throws ForeignSessionNotFoundException
         * @throws InvalidSearchMethodException
         * @throws TelegramException
         * @throws DatabaseException
         * @throws Exception
         * @noinspection DuplicatedCode
         * @noinspection PhpUndefinedClassInspection
         */
        public function execute()
        {
            $TelegramClientManager = new TelegramClientManager();
            $DeepAnalytics = new DeepAnalytics();

            $ChatObject = Chat::fromArray($this->getMessage()->getChat()->getRawData());
            $UserObject = User::fromArray($this->getMessage()->getFrom()->getRawData());

            try
            {
                $TelegramClient = $TelegramClientManager->getTelegramClientManager()->registerClient($ChatObject, $UserObject);

                // Define and update chat client
                $ChatClient = $TelegramClientManager->getTelegramClientManager()->registerChat($ChatObject);
                $TelegramClientManager->getTelegramClientManager()->updateClient($ChatClient);

                // Define and update user client
                $UserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($UserObject);
                $TelegramClientManager->getTelegramClientManager()->updateClient($UserClient);

                // Define and update the forwarder if available
                if($this->getMessage()->getForwardFrom() !== null)
                {
                    $ForwardUserObject = User::fromArray($this->getMessage()->getForwardFrom()->getRawData());
                    $ForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($ForwardUserObject);
                    $TelegramClientManager->getTelegramClientManager()->updateClient($ForwardUserClient);
                }
            }
            catch(Exception $e)
            {
                //$TelegramClientManager->getDatabase()->close();
                return null;
            }

            $DeepAnalytics->tally('tg_lydia', 'messages', 0);
            $DeepAnalytics->tally('tg_lydia', 'messages', (int)$TelegramClient->getChatId());

            if($this->getMessage() == null)
            {
                //$TelegramClientManager->getDatabase()->close();
                return null;
            }

            if(self::chance(3))
            {
                $DeepAnalytics->tally('tg_lydia', 'chance_hit', 0);
                $DeepAnalytics->tally('tg_lydia', 'chance_hit', (int)$TelegramClient->getChatId());
            }
            else
            {
                if($this->getMessage()->getChat()->isGroupChat() || $this->getMessage()->getChat()->isSuperGroup())
                {
                    if($this->getMessage()->getReplyToMessage() !== null)
                    {
                        if($this->getMessage()->getReplyToMessage()->getFrom()->getUsername() !== null)
                        {
                            if($this->getMessage()->getReplyToMessage()->getFrom()->getUsername() !== TELEGRAM_BOT_NAME)
                            {
                                //$TelegramClientManager->getDatabase()->close();
                                return null;
                            }
                        }
                    }
                    elseif(stripos($this->getMessage()->getText(true), "lydia") == false)
                    {
                        //$TelegramClientManager->getDatabase()->close();
                        return null;
                    }
                }
            }

            $CoffeeHouse = new CoffeeHouse();
            $Bot = new Cleverbot($CoffeeHouse);

            Request::sendChatAction([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "action" => ChatAction::TYPING
            ]);

            if(isset($ChatClient->SessionData->Data['lydia_default_language']) == false)
            {
                if(is_null($this->getMessage()->getFrom()->getLanguageCode()))
                {
                    $ChatClient->SessionData->Data['lydia_default_language'] = 'en';
                }
                else
                {
                    $ChatClient->SessionData->Data['lydia_default_language'] = $this->getMessage()->getFrom()->getLanguageCode();
                }

                $TelegramClientManager->getTelegramClientManager()->updateClient($ChatClient);
            }

            // Check if the Telegram Client has a session ID
            if(isset($ChatClient->SessionData->Data['lydia_session_id']) == false)
            {
                $Bot->newSession($ChatClient->SessionData->Data['lydia_default_language']);
                $ChatClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                $TelegramClientManager->getTelegramClientManager()->updateClient($ChatClient);

                $DeepAnalytics->tally('tg_lydia', 'created_sessions', 0);
                $DeepAnalytics->tally('tg_lydia', 'created_sessions', (int)$ChatClient->getChatId());
            }
            else
            {
                $Bot->loadSession($ChatClient->SessionData->Data['lydia_session_id']);
                if((int)time() > $Bot->getSession()->Expires)
                {
                    $Bot->newSession($ChatClient->SessionData->Data['lydia_default_language']);
                    $ChatClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                    $TelegramClientManager->getTelegramClientManager()->updateClient($ChatClient);

                    $DeepAnalytics->tally('tg_lydia', 'created_sessions', 0);
                    $DeepAnalytics->tally('tg_lydia', 'created_sessions', (int)$ChatClient->getChatId());
                }
            }

            if($this->getMessage()->getText(true) == null)
            {
                //$TelegramClientManager->getDatabase()->close();
                //$CoffeeHouse->getDatabase()->close();

                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "text" => "Yeah, i don't understand this, sorry."
                ]);
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

                $Bot->newSession($ChatClient->SessionData->Data['lydia_default_language']);
                $ChatClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                $TelegramClientManager->getTelegramClientManager()->updateClient($ChatClient);

                $DeepAnalytics->tally('tg_lydia', 'created_sessions', 0);
                $DeepAnalytics->tally('tg_lydia', 'created_sessions', (int)$ChatClient->getChatId());

                // Rethink the output
                $Output = $Bot->think($this->getMessage()->getText(true));
            }

            $DeepAnalytics->tally('tg_lydia', 'ai_responses', 0);
            $DeepAnalytics->tally('tg_lydia', 'ai_responses', (int)$ChatClient->getChatId());

            //$TelegramClientManager->getDatabase()->close();
            //$CoffeeHouse->getDatabase()->close();

            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "text" => $Output
            ]);
        }

        private static function chance($percent)
        {
            return mt_rand(0, 299) < $percent;
        }
    }

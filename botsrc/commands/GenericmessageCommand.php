<?php

    /** @noinspection PhpUndefinedClassInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use CoffeeHouse\Bots\Cleverbot;
    use CoffeeHouse\Exceptions\BotSessionException;
    use CoffeeHouse\Exceptions\ForeignSessionNotFoundException;
    use CoffeeHouse\Exceptions\InvalidSearchMethodException;
    use Exception;
    use Longman\TelegramBot\ChatAction;
    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use LydiaTelegramBot;
    use TelegramClientManager\Exceptions\DatabaseException;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;

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
        protected $version = '1.0.2';

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
            $TelegramClientManager = LydiaTelegramBot::getTelegramClientManager();

            $ChatObject = Chat::fromArray($this->getMessage()->getChat()->getRawData());
            $UserObject = User::fromArray($this->getMessage()->getFrom()->getRawData());

            try
            {
                /** @noinspection PhpUnusedLocalVariableInspection */
                $TelegramClient = $TelegramClientManager->getTelegramClientManager()->registerClient($ChatObject, $UserObject);

                // Define and update chat client
                $ChatClient = $TelegramClientManager->getTelegramClientManager()->registerChat($ChatObject);

                // Define and update user client
                /** @noinspection PhpUnusedLocalVariableInspection */
                $UserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($UserObject);

                // Define and update the forwarder if available
                if($this->getMessage()->getForwardFrom() !== null)
                {
                    $ForwardUserObject = User::fromArray($this->getMessage()->getForwardFrom()->getRawData());
                    $TelegramClientManager->getTelegramClientManager()->registerUser($ForwardUserObject);
                }
            }
            catch(Exception $e)
            {
                return null;
            }

            if($this->getMessage() == null)
            {
                return null;
            }

            if($this->getMessage()->getText(true) == null)
            {
                return null;
            }

            if(strlen($this->getMessage()->getText(true)) == 0)
            {
                return null;
            }

            $IsMatch = (bool)preg_match("/\blydia\b/i", strtolower($this->getMessage()->getText(true)));

            if($this->getMessage()->getChat()->isGroupChat() || $this->getMessage()->getChat()->isSuperGroup())
            {
                if($this->getMessage()->getReplyToMessage() !== null)
                {
                    $ReplyUsername = 'None';
                    if($this->getMessage()->getReplyToMessage()->getFrom()->getUsername() !== null)
                    {
                        $ReplyUsername =  $this->getMessage()->getReplyToMessage()->getFrom()->getUsername();
                    }

                    if(strtolower($ReplyUsername) !== strtolower(TELEGRAM_BOT_NAME))
                    {
                        return null;
                    }
                }
                elseif($IsMatch == false)
                {
                    return null;
                }
            }

            $CoffeeHouse = LydiaTelegramBot::getCoffeeHouse();
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

            $DeepAnalytics = LydiaTelegramBot::getDeepAnalytics();

            // Check if the Telegram Client has a session ID
            try
            {
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

                    if($Bot->getSession()->Available == false)
                    {
                        $Bot->newSession($ChatClient->SessionData->Data['lydia_default_language']);
                        $ChatClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                        $TelegramClientManager->getTelegramClientManager()->updateClient($ChatClient);

                        $DeepAnalytics->tally('tg_lydia', 'created_sessions', 0);
                        $DeepAnalytics->tally('tg_lydia', 'created_sessions', (int)$ChatClient->getChatId());
                    }
                }
            }
            catch(Exception $e)
            {
                // If a session error raises
                $Bot->newSession($ChatClient->SessionData->Data['lydia_default_language']);
                $ChatClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                $TelegramClientManager->getTelegramClientManager()->updateClient($ChatClient);

                $DeepAnalytics->tally('tg_lydia', 'created_sessions', 0);
                $DeepAnalytics->tally('tg_lydia', 'created_sessions', (int)$ChatClient->getChatId());
            }

            if($this->getMessage()->getText(true) == null)
            {
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
                try
                {
                    $Output = $Bot->think($this->getMessage()->getText(true));
                }
                catch(Exception $e)
                {
                    // Bug fix: This raises another unhandled exception due to a session error.
                    $Bot->getSession()->Available = false;
                    $CoffeeHouse->getForeignSessionsManager()->updateSession($Bot->getSession());
                    $exception_id = LydiaTelegramBot::getLogHandler()->logException($e, get_class($this));

                    return Request::sendMessage([
                        "chat_id" => $this->getMessage()->getChat()->getId(),
                        "reply_to_message_id" => $this->getMessage()->getMessageId(),
                        "text" => "I'm sorry, I think an error occurred with our chat session, please try again later. ($exception_id)"
                    ]);
                }
            }

            $DeepAnalytics->tally('tg_lydia', 'ai_responses', 0);
            $DeepAnalytics->tally('tg_lydia', 'ai_responses', (int)$ChatClient->getChatId());

            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "text" => $Output
            ]);
        }

        private static function chance($percent)
        {
            return mt_rand(0, 5000) < $percent;
        }
    }

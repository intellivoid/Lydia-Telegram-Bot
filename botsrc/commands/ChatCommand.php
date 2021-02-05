<?php

    /** @noinspection PhpUnusedLocalVariableInspection */
    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection DuplicatedCode */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use CoffeeHouse\Bots\Cleverbot;
    use CoffeeHouse\Exceptions\BotSessionException;
    use CoffeeHouse\Exceptions\DatabaseException;
    use CoffeeHouse\Exceptions\ForeignSessionNotFoundException;
    use CoffeeHouse\Exceptions\InvalidSearchMethodException;
    use CoffeeHouse\Exceptions\LocalSessionNotFoundException;
    use CoffeeHouse\Exceptions\NoResultsFoundException;
    use Exception;
    use Longman\TelegramBot\ChatAction;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\Message;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use LydiaTelegramBot;
    use TelegramClientManager\Exceptions\InvalidSearchMethod;
    use TelegramClientManager\Exceptions\TelegramClientNotFoundException;
    use TelegramClientManager\Managers\TelegramClientManager;
    use TelegramClientManager\Objects\TelegramClient;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;

    /**
     * Chat command
     *
     * Responds to a user using the /chat command
     */
    class ChatCommand extends UserCommand
    {
        /**
         * @var string
         */
        protected $name = 'chat';

        /**
         * @var string
         */
        protected $description = 'Responds to a user using the /chat command';

        /**
         * @var string
         */
        protected $usage = '/chat';

        /**
         * @var string
         */
        protected $version = '1.0.1';

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
         * @throws Exception
         */
        public function execute(): ?ServerResponse
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
                    $ForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($ForwardUserObject);
                }
            }
            catch(Exception $e)
            {
                $exception_id = LydiaTelegramBot::getLogHandler()->logException($e, get_class($this));
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $exception_id . "</code>\n" .
                        "Object: <code>Commands/newsession.bin</code>"
                ]);
            }

            $DeepAnalytics = LydiaTelegramBot::getDeepAnalytics();
            $DeepAnalytics->tally('tg_lydia', 'messages', 0);
            $DeepAnalytics->tally('tg_lydia', 'messages', (int)$ChatClient->getChatId());

            if(strlen($this->getMessage()->getText(true)) == 0)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "That's not how you use the chat command!\n\n".
                        "<code>/chat Hello</code>"
                ]);
            }

            return $this->processThought($this->getMessage(), $TelegramClientManager, $ChatClient, $this->getMessage()->getText(true));
        }

        /**
         * Processes a thought process
         *
         * @param Message $message
         * @param \TelegramClientManager\TelegramClientManager $telegramClientManager
         * @param TelegramClient $chatClient
         * @param string $input
         * @return ServerResponse|null
         * @throws BotSessionException
         * @throws DatabaseException
         * @throws ForeignSessionNotFoundException
         * @throws InvalidSearchMethodException
         * @throws LocalSessionNotFoundException
         * @throws NoResultsFoundException
         * @throws TelegramException
         * @throws \TelegramClientManager\Exceptions\DatabaseException
         * @throws InvalidSearchMethod
         * @throws TelegramClientNotFoundException
         */
        public function processThought(Message $message, \TelegramClientManager\TelegramClientManager $telegramClientManager, TelegramClient $chatClient, string $input): ?ServerResponse
        {
            $CoffeeHouse = LydiaTelegramBot::getCoffeeHouse();
            $Bot = new Cleverbot($CoffeeHouse);

            Request::sendChatAction([
                "chat_id" => $message->getChat()->getId(),
                "action" => ChatAction::TYPING
            ]);

            if(isset($chatClient->SessionData->Data['lydia_default_language']) == false)
            {
                if(is_null($message->getFrom()->getLanguageCode()))
                {
                    $chatClient->SessionData->Data['lydia_default_language'] = 'en';
                }
                else
                {
                    $chatClient->SessionData->Data['lydia_default_language'] = $message->getFrom()->getLanguageCode();
                }

                $telegramClientManager->getTelegramClientManager()->updateClient($chatClient);
            }

            $DeepAnalytics = LydiaTelegramBot::getDeepAnalytics();

            // Check if the Telegram Client has a session ID
            try
            {
                if(isset($chatClient->SessionData->Data['lydia_session_id']) == false)
                {
                    $Bot->newSession($chatClient->SessionData->Data['lydia_default_language']);
                    $chatClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                    $telegramClientManager->getTelegramClientManager()->updateClient($chatClient);

                    $DeepAnalytics->tally('tg_lydia', 'created_sessions', 0);
                    $DeepAnalytics->tally('tg_lydia', 'created_sessions', (int)$chatClient->getChatId());
                }
                else
                {
                    $Bot->loadSession($chatClient->SessionData->Data['lydia_session_id']);

                    if((int)time() > $Bot->getSession()->Expires)
                    {
                        $Bot->newSession($chatClient->SessionData->Data['lydia_default_language']);
                        $chatClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                        $telegramClientManager->getTelegramClientManager()->updateClient($chatClient);

                        $DeepAnalytics->tally('tg_lydia', 'created_sessions', 0);
                        $DeepAnalytics->tally('tg_lydia', 'created_sessions', (int)$chatClient->getChatId());
                    }

                    if($Bot->getSession()->Available == false)
                    {
                        $Bot->newSession($chatClient->SessionData->Data['lydia_default_language']);
                        $chatClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                        $telegramClientManager->getTelegramClientManager()->updateClient($chatClient);

                        $DeepAnalytics->tally('tg_lydia', 'created_sessions', 0);
                        $DeepAnalytics->tally('tg_lydia', 'created_sessions', (int)$chatClient->getChatId());
                    }
                }
            }
            catch(Exception $e)
            {
                // If a session error raises
                $Bot->newSession($chatClient->SessionData->Data['lydia_default_language']);
                $chatClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                $telegramClientManager->getTelegramClientManager()->updateClient($chatClient);

                $DeepAnalytics->tally('tg_lydia', 'created_sessions', 0);
                $DeepAnalytics->tally('tg_lydia', 'created_sessions', (int)$chatClient->getChatId());
            }

            if($message->getText(true) == null)
            {
                return Request::sendMessage([
                    "chat_id" => $message->getChat()->getId(),
                    "reply_to_message_id" => $message->getMessageId(),
                    "text" => "Yeah, i don't understand this, sorry."
                ]);
            }

            try
            {
                $Output = $Bot->think($input);
            }
            catch(BotSessionException $botSessionException)
            {
                // Mark is unavailable
                $Bot->getSession()->Available = false;
                $CoffeeHouse->getForeignSessionsManager()->updateSession($Bot->getSession());

                $Bot->newSession($chatClient->SessionData->Data['lydia_default_language']);
                $chatClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                $telegramClientManager->getTelegramClientManager()->updateClient($chatClient);

                $DeepAnalytics->tally('tg_lydia', 'created_sessions', 0);
                $DeepAnalytics->tally('tg_lydia', 'created_sessions', (int)$chatClient->getChatId());

                // Rethink the output
                try
                {
                    $Output = $Bot->think($input);
                }
                catch(Exception $e)
                {
                    // Bug fix: This raises another unhandled exception due to a session error.
                    $Bot->getSession()->Available = false;
                    $CoffeeHouse->getForeignSessionsManager()->updateSession($Bot->getSession());
                    $exception_id = LydiaTelegramBot::getLogHandler()->logException($e, get_class($this));

                    return Request::sendMessage([
                        "chat_id" => $message->getChat()->getId(),
                        "reply_to_message_id" => $message->getMessageId(),
                        "text" => "I'm sorry, I think an error occurred with our chat session, please try again later. ($exception_id)"
                    ]);
                }
            }

            $DeepAnalytics->tally('tg_lydia', 'ai_responses', 0);
            $DeepAnalytics->tally('tg_lydia', 'ai_responses', (int)$chatClient->getChatId());

            return Request::sendMessage([
                "chat_id" => $message->getChat()->getId(),
                "reply_to_message_id" => $message->getMessageId(),
                "text" => $Output
            ]);
        }
    }
<?php

    /** @noinspection PhpUnusedLocalVariableInspection */
    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection DuplicatedCode */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use CoffeeHouse\Bots\Cleverbot;
    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Exceptions\BotSessionException;
    use CoffeeHouse\Exceptions\DatabaseException;
    use CoffeeHouse\Exceptions\ForeignSessionNotFoundException;
    use CoffeeHouse\Exceptions\InvalidSearchMethodException;
    use DeepAnalytics\DeepAnalytics;
    use Exception;
    use Longman\TelegramBot\ChatAction;
    use Longman\TelegramBot\Commands\UserCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;
    use TelegramClientManager\TelegramClientManager;

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
        public function execute()
        {
            $TelegramClientManager = new TelegramClientManager();

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
                //$TelegramClientManager->getDatabase()->close();
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $e->getCode() . "</code>\n" .
                        "Object: <code>Commands/newsession.bin</code>"
                ]);
            }

            $DeepAnalytics = new DeepAnalytics();
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

            Request::sendChatAction([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "action" => ChatAction::TYPING
            ]);

            $CoffeeHouse = new CoffeeHouse();
            $Bot = new Cleverbot($CoffeeHouse);

            // Check the default language
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

            // Check if the Chat Client has a session ID
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
                // If not, create one
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

            // Finally, think a thought
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

            //$CoffeeHouse->getDatabase()->close();
            //$TelegramClientManager->getDatabase()->close();

            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "text" => $Output
            ]);

        }
    }
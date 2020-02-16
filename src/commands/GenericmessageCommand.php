<?php

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use CoffeeHouse\Bots\Cleverbot;
    use CoffeeHouse\CoffeeHouse;
    use CoffeeHouse\Exceptions\BotSessionException;
    use CoffeeHouse\Exceptions\ForeignSessionNotFoundException;
    use CoffeeHouse\Exceptions\InvalidSearchMethodException;
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
     * Start command
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
         * Executes the generic message command
         *
         * @return ServerResponse|null
         * @throws BotSessionException
         * @throws \CoffeeHouse\Exceptions\DatabaseException
         * @throws ForeignSessionNotFoundException
         * @throws InvalidSearchMethodException
         * @throws TelegramException
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

            if($this->getMessage() == null)
            {
                return null;
            }

            $CoffeeHouse = new CoffeeHouse();

            $i = 'i';
            $needle = 'lydia';

            if($this->getMessage()->getChat()->isGroupChat() || $this->getMessage()->getChat()->isSuperGroup())
            {
                if($this->getMessage()->getReplyToMessage() !== null)
                {
                    $ReplyUsername = 'None';
                    if($this->getMessage()->getReplyToMessage()->getFrom()->getUsername() !== null)
                    {
                        $ReplyUsername =  $this->getMessage()->getReplyToMessage()->getFrom()->getUsername();
                    }

                    if($ReplyUsername !== TELEGRAM_BOT_NAME)
                    {
                        return null;
                    }
                }
                elseif(!preg_match("/\b{$needle}\b/{$i}", strtolower($this->getMessage()->getText(true))))
                {
                    return null;
                }
            }


            $Bot = new Cleverbot($CoffeeHouse);

            Request::sendChatAction([
                'chat_id' => $this->getMessage()->getChat()->getId(),
                'action' => ChatAction::TYPING
            ]);

            if(isset($TelegramClient->SessionData->Data['lydia_default_language']) == false)
            {
                $TelegramClient->SessionData->Data['lydia_default_language'] = 'en';
                $TelegramClientManager->getTelegramClientManager()->updateClient($TelegramClient);
            }

            // Check if the Telegram Client has a session ID
            if(isset($TelegramClient->SessionData->Data['lydia_session_id']) == false)
            {
                $Bot->newSession('en');
                $TelegramClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                $TelegramClientManager->getTelegramClientManager()->updateClient($TelegramClient);

            }
            else
            {
                $Bot->loadSession($TelegramClient->SessionData->Data['lydia_session_id']);
                if((int)time() > $Bot->getSession()->Expires)
                {
                    $Bot->newSession('en');
                    $TelegramClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                    $TelegramClientManager->getTelegramClientManager()->updateClient($TelegramClient);
                }
            }

            try
            {
                if($this->getMessage()->getText(true) == NULL)
                {
                    $Output = "I do not understand this. Sorry";
                }
                else
                {
                    $Output = $Bot->think($this->getMessage()->getText(true));
                }
            }
            catch(BotSessionException $botSessionException)
            {
                // Mark is unavailable
                $Bot->getSession()->Available = false;
                $CoffeeHouse->getForeignSessionsManager()->updateSession($Bot->getSession());

                $Bot->newSession('en');
                $TelegramClient->SessionData->Data['lydia_session_id'] = $Bot->getSession()->SessionID;
                $TelegramClientManager->getTelegramClientManager()->updateClient($TelegramClient);

                // Rethink the output
                if($this->getMessage()->getText(true) == NULL)
                {
			        $Output = "I do not get you. Sorry.";
		        }
                else
                {
			        $Output = $Bot->think($this->getMessage()->getText(true));
		        }
            }

            $data = [
                'chat_id' => $this->getMessage()->getChat()->getId(),
                'reply_to_message_id' => $this->getMessage()->getMessageId(),
                'text' => $Output . "\n\n"
            ];

            return Request::sendMessage($data);

        }
    }

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
         */
        public function execute()
        {
            $TelegramClientManager = new TelegramClientManager();
            $DeepAnalytics = new DeepAnalytics();
            $VerificationFailed = false;

            try
            {
                $TelegramClient = $TelegramClientManager->getTelegramClientManager()->registerClient(
                    Chat::fromArray($this->getMessage()->getChat()->getRawData()),
                    User::fromArray($this->getMessage()->getFrom()->getRawData())
                );
            }
            catch(Exception $e)
            {
                $VerificationFailed = true;
            }

            if($this->getMessage() == null)
            {
                exit(0);
            }

            $LydiaChanEnabled = false;
            if($VerificationFailed == false)
            {
                $DeepAnalytics->tallyMonthly("tg_lydia", "messages", 0);
                $DeepAnalytics->tallyHourly("tg_lydia", "messages", 0);
                $DeepAnalytics->tallyMonthly("tg_lydia", "messages", (int)$TelegramClient->getChatId());
                $DeepAnalytics->tallyHourly("tg_lydia", "messages", (int)$TelegramClient->getChatId());

                if(isset($TelegramClient->SessionData->Data['lydiachan']))
                {
                    if($TelegramClient->SessionData->Data['lydiachan'])
                    {
                        $LydiaChanEnabled = true;
                    }
                }

                if(strtolower($this->getMessage()->getText(true)) == "lydiachan")
                {
                    if(isset($TelegramClient->SessionData->Data['lydiachan']))
                    {
                        if($TelegramClient->SessionData->Data['lydiachan'] == false)
                        {
                            $TelegramClient->SessionData->Data['lydiachan'] = true;
                        }
                        else
                        {
                            $TelegramClient->SessionData->Data['lydiachan'] = false;
                        }
                    }
                    else
                    {
                        $TelegramClient->SessionData->Data['lydiachan'] = true;
                    }

                    $TelegramClientManager->getTelegramClientManager()->updateClient($TelegramClient);
                    if($TelegramClient->SessionData->Data['lydiachan'])
                    {
                        $data = [
                            'chat_id' => $this->getMessage()->getChat()->getId(),
                            'reply_to_message_id' => $this->getMessage()->getMessageId(),
                            'text' => "Onnichann!! //>.<// LydiaChan at your service!!1! :3"
                        ];

                        return Request::sendMessage($data);
                    }
                    else
                    {
                        $data = [
                            'chat_id' => $this->getMessage()->getChat()->getId(),
                            'reply_to_message_id' => $this->getMessage()->getMessageId(),
                            'text' => "o-ok oniicchannn sorrrryyy!!! i wont bother you againnnnn"
                        ];

                        return Request::sendMessage($data);
                    }
                }
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
                        exit(0);
                    }
                }
                elseif(!preg_match("/\b{$needle}\b/{$i}", strtolower($this->getMessage()->getText(true))))
                {
                    exit(0);
                }
            }

            if($VerificationFailed)
            {
                $data = [
                    'chat_id' => $this->getMessage()->getChat()->getId(),
                    'reply_to_message_id' => $this->getMessage()->getMessageId(),
                    'text' => "Oops! Something went wrong! (Error 99) contact someone in @IntellivoidDev."
                ];

                return Request::sendMessage($data);
            }

            $Bot = new Cleverbot($CoffeeHouse);

            Request::sendChatAction([
                'chat_id' => $this->getMessage()->getChat()->getId(),
                'action' => ChatAction::TYPING
            ]);

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

                $Bot->newSession($TelegramClient->SessionData->Data['lydia_default_language']);
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

            if($LydiaChanEnabled)
            {
                $faces = ["(・`ω´・)", ";;w;;", "owo", "UwU", ">w<", "^w^"];
                $Output = str_replace('ove', 'uv', $Output);
                $Output = str_replace('r', 'w', $Output);
                $Output = str_replace('R', 'W', $Output);
                $Output = str_replace('.', '..', $Output);
                $Output = str_replace('!', '!!', $Output);
                $Output = str_ireplace('brother', 'onichan', $Output);
                $Output = str_ireplace('hi', 'hiiii', $Output);
                $Output = str_ireplace('hello', 'hewweoo--owo', $Output);
                $Output = str_ireplace(',', ',,', $Output);
                $Output = str_ireplace('lydia', 'lydiachan', $Output);
                $Output = str_ireplace(':(', ';-;', $Output);
                $Output = str_ireplace(':)', ':3', $Output);
                $Output = str_ireplace('lol', 'wow', $Output);
                $Output = str_ireplace('', ':3', $Output);

                $face = $faces[mt_rand(0, count($faces) - 1)];
                if(rand(0, 100) < 40)
                {
                    if(rand(0, 100) > 40)
                    {
                        $Output = $face . ' ' . $Output;
                    }
                    else
                    {
                        $Output = $Output . ' ' . $face;
                    }
                }
            }

            $data = [
                'chat_id' => $this->getMessage()->getChat()->getId(),
                'reply_to_message_id' => $this->getMessage()->getMessageId(),
                'text' => $Output . "\n\n"
            ];

            $DeepAnalytics->tallyMonthly("tg_lydia", "ai_responses", 0);
            $DeepAnalytics->tallyHourly("tg_lydia", "ai_responses", 0);
            $DeepAnalytics->tallyMonthly("tg_lydia", "ai_responses", (int)$TelegramClient->getChatId());
            $DeepAnalytics->tallyHourly("tg_lydia", "ai_responses", (int)$TelegramClient->getChatId());

            return Request::sendMessage($data);
        }
    }

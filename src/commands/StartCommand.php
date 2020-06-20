<?php

    /** @noinspection PhpUndefinedClassInspection */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use DeepAnalytics\DeepAnalytics;
    use Exception;
    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;
    use TelegramClientManager\TelegramClientManager;

    /**
     * Start command
     *
     * Gets executed when a user first starts using the bot.
     */
    class StartCommand extends SystemCommand
    {
        /**
         * @var string
         */
        protected $name = 'start';

        /**
         * @var string
         */
        protected $description = 'Start command';

        /**
         * @var string
         */
        protected $usage = '/start';

        /**
         * @var string
         */
        protected $version = '2.0.0';

        /**
         * @var bool
         */
        protected $private_only = false;

        /**
         * Command execute method
         *
         * @return ServerResponse
         * @throws TelegramException
         * @noinspection DuplicatedCode
         */
        public function execute()
        {
            $TelegramClientManager = new TelegramClientManager();

            $ChatObject = Chat::fromArray($this->getMessage()->getChat()->getRawData());
            $UserObject = User::fromArray($this->getMessage()->getFrom()->getRawData());

            try
            {
                $TelegramClient = $TelegramClientManager->getTelegramClientManager()->registerClient($ChatObject, $UserObject);

                // Define and update chat client
                /** @noinspection PhpUnusedLocalVariableInspection */
                $ChatClient = $TelegramClientManager->getTelegramClientManager()->registerChat($ChatObject);

                // Define and update user client
                /** @noinspection PhpUnusedLocalVariableInspection */
                $UserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($UserObject);

                // Define and update the forwarder if available
                if($this->getMessage()->getForwardFrom() !== null)
                {
                    $ForwardUserObject = User::fromArray($this->getMessage()->getForwardFrom()->getRawData());
                    /** @noinspection PhpUnusedLocalVariableInspection */
                    $ForwardUserClient = $TelegramClientManager->getTelegramClientManager()->registerUser($ForwardUserObject);
                }
            }
            catch(Exception $e)
            {
                return Request::sendMessage([
                    "chat_id" => $this->getMessage()->getChat()->getId(),
                    "reply_to_message_id" => $this->getMessage()->getMessageId(),
                    "parse_mode" => "html",
                    "text" =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDiscussions\n\n" .
                        "Error Code: <code>" . $e->getCode() . "</code>\n" .
                        "Object: <code>Commands/start.bin</code>"
                ]);
            }

            $DeepAnalytics = new DeepAnalytics();
            $DeepAnalytics->tally('tg_lydia', 'messages', 0);
            $DeepAnalytics->tally('tg_lydia', 'messages', (int)$TelegramClient->getChatId());

            return Request::sendMessage([
                "chat_id" => $this->getMessage()->getChat()->getId(),
                "reply_to_message_id" => $this->getMessage()->getMessageId(),
                "text" =>
                    "Hi! I'm Lydia, a Machine Learning chat bot that isn't based off a crappy AI/ML library or service from Microsoft or any of the big companies.\n\n" .
                    "I'm based off a machine learning & artificial intelligence engine called CoffeeHouse! this whole project was created from scratch by @Intellivoid\n\n" .
                    "You can add me to groups and mention my name or talk to me here, we can talk about anything!"
            ]);

        }
    }
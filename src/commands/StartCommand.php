<?php

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
                    'text' =>
                        "Oops! Something went wrong! contact someone in @IntellivoidDev"
                ];

                return Request::sendMessage($data);
            }

            $DeepAnalytics = new DeepAnalytics();
            $DeepAnalytics->tally('tg_lydia', 'messages', 0);
            $DeepAnalytics->tally('tg_lydia', 'messages', (int)$TelegramClient->getChatId());

            $data = [
                'chat_id' => $this->getMessage()->getChat()->getId(),
                'text' =>
                    "Hi! I'm Lydia, a Machine Learning chat bot that isn't based off a crappy AI/ML library or service from Microsoft or any of the big companies.\n\n" .
                    "I'm based off a machine learning & artificial intelligence engine called CoffeeHouse! this whole project was created from scratch by @Intellivoid\n\n" .
                    "You can add me to groups and mention my name or talk to me here, we can talk about anything!"
            ];

            return Request::sendMessage($data);

        }
    }
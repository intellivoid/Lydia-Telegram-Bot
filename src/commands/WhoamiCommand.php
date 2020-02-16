<?php

    namespace Longman\TelegramBot\Commands\SystemCommands;
    use Exception;
    use Longman\TelegramBot\ChatAction;
    use Longman\TelegramBot\Commands\SystemCommand;
    use Longman\TelegramBot\Entities\ServerResponse;
    use Longman\TelegramBot\Exception\TelegramException;
    use Longman\TelegramBot\Request;
    use TelegramClientManager\Objects\TelegramClient\Chat;
    use TelegramClientManager\Objects\TelegramClient\User;
    use TelegramClientManager\TelegramClientManager;

    /**
     * Whoami Command
     *
     * Gets executed when a user sends /whoami
     */
    class WhoamiCommand extends SystemCommand
    {
        /**
         * @var string
         */
        protected $name = 'whoami';

        /**
         * @var string
         */
        protected $description = 'Returns information stored about you';

        /**
         * @var string
         */
        protected $usage = '/whoami';

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

            $TelegramClientData = $TelegramClient->toArray();
            $TelegramClientData['id'] = hash('sha256', $TelegramClient->ID . 'IV');

            /** @noinspection PhpComposerExtensionStubsInspection */
            $data = [
                'chat_id' => $this->getMessage()->getChat()->getId(),
                'reply_to_message_id' => $this->getMessage()->getMessageId(),
                'text' => json_encode($TelegramClientData, JSON_PRETTY_PRINT)
            ];

            return Request::sendMessage($data);

        }
    }
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

            $ChatCommand = new ChatCommand($this->telegram);
            return $ChatCommand->processThought($this->getMessage(), $TelegramClientManager, $ChatClient, $this->getMessage()->getText(true));
        }

        private static function chance($percent)
        {
            return mt_rand(0, 5000) < $percent;
        }
    }

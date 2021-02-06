<?php

    /** @noinspection PhpUnusedLocalVariableInspection */
    /** @noinspection PhpUndefinedClassInspection */
    /** @noinspection DuplicatedCode */

    namespace Longman\TelegramBot\Commands\SystemCommands;

    use CoffeeHouse\Abstracts\EmotionType;
    use CoffeeHouse\Bots\Cleverbot;
    use CoffeeHouse\Exceptions\BotSessionException;
    use CoffeeHouse\Exceptions\DatabaseException;
    use CoffeeHouse\Exceptions\ForeignSessionNotFoundException;
    use CoffeeHouse\Exceptions\InvalidSearchMethodException;
    use CoffeeHouse\Exceptions\LocalSessionNotFoundException;
    use CoffeeHouse\Exceptions\NoResultsFoundException;
    use CoffeeHouse\Objects\LargeGeneralization;
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

            $enable_lydiachan = false;

            if(strtolower($input) == "lydiachan")
            {
                if(isset($chatClient->SessionData->Data["lydiachan_enabled"]))
                {
                    if($chatClient->SessionData->Data["lydiachan_enabled"])
                    {
                        $chatClient->SessionData->Data["lydiachan_enabled"] = false;
                    }
                    else
                    {
                        $chatClient->SessionData->Data["lydiachan_enabled"] = true;
                    }
                }
                else
                {
                    $chatClient->SessionData->Data["lydiachan_enabled"] = true;
                }

                $telegramClientManager->getTelegramClientManager()->updateClient($chatClient);
            }

            if(isset($chatClient->SessionData->Data["lydiachan_enabled"]))
            {
                $enable_lydiachan = (bool)$chatClient->SessionData->Data["lydiachan_enabled"];
            }

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

            $predictionEmojiFeedback = $this->predictionEmojiFeedback($Bot->getLocalSession()->EmotionLargeGeneralization, $enable_lydiachan);
            if($predictionEmojiFeedback == null)
            {
                $predictionEmojiFeedback = (string)null;
            }
            else
            {
                $predictionEmojiFeedback = " $predictionEmojiFeedback";
            }


            if($enable_lydiachan)
                $Output = $this->owofiy($Output);

            return Request::sendMessage([
                "chat_id" => $message->getChat()->getId(),
                "reply_to_message_id" => $message->getMessageId(),
                "text" => $Output . $predictionEmojiFeedback
            ]);
        }

        /**
         * Determine the chance
         *
         * @param $percent
         * @return bool
         */
        private static function chance($percent)
        {
            return mt_rand(0, 5000) < $percent;
        }

        private function predictionEmojiFeedback(LargeGeneralization $emotionLargeGeneralization, bool $weebMode=false): ?string
        {
            $emotion_priority = 0;

            if(self::chance(800)) $emotion_priority = 1;

            switch($emotionLargeGeneralization->Probabilities[$emotion_priority]->Label)
            {
                case EmotionType::Sadness:
                    $emojis = [
                        "\u{1F614}",
                        "\u{1F622}",
                        "\u{1F61E}",
                        "\u{1F62D}",
                    ];

                    $weeb = [
                        "(ノ_<。)",
                        "(μ_μ)",
                        "o(TヘTo)",
                        "( ﾟ，_ゝ｀)",
                        "( ﾟ，_ゝ｀)",
                        "(T_T)"
                    ];

                    $chance = false;

                    if($weebMode)
                    {
                        $chance = self::chance(4000);
                    }
                    else
                    {
                        $chance =self::chance(2500);
                    }

                    if($chance)
                    {
                        if($weebMode)
                            return $weeb[array_rand($weeb)];
                        return $emojis[array_rand($emojis)];
                    }

                    break;

                case EmotionType::Happiness:
                    $emojis = [
                        "\u{1F603}",
                        "\u{1F60A}",
                        "\u{1F601}",
                        "\u{1F63A}",
                    ];

                    $weeb = [
                        "╰(▔∀▔)╯",
                        "(*≧ω≦*)",
                        "٩(◕‿◕)۶",
                        "(〃＾▽＾〃)",
                        "＼(٥⁀▽⁀ )／",
                        "(ﾉ◕ヮ◕)ﾉ*:･ﾟ✧",
                    ];

                    $chance = false;

                    if($weebMode)
                    {
                        $chance = self::chance(4000);
                    }
                    else
                    {
                        $chance =self::chance(2500);
                    }

                    if($chance)
                    {
                        if($weebMode)
                            return $weeb[array_rand($weeb)];
                        return $emojis[array_rand($emojis)];
                    }
                    break;

                case EmotionType::Anger:
                    $emojis = [
                        "\u{1F621}",
                        "\u{1F620}",
                        "\u{1F624}",
                        "\u{1F92C}",
                    ];

                    $weeb = [
                        "(・`ω´・)",
                        "(＃`Д´)",
                        "(`皿´＃)",
                        "┌∩┐(◣_◢)┌∩┐",
                        "(凸ಠ益ಠ)凸",
                    ];

                    $chance = false;

                    if($weebMode)
                    {
                        $chance = self::chance(4000);
                    }
                    else
                    {
                        $chance = self::chance(2500);
                    }

                    if($chance)
                    {
                        if($weebMode)
                            return $weeb[array_rand($weeb)];
                        return $emojis[array_rand($emojis)];
                    }


                    break;

                case EmotionType::Affection:
                    $emojis = [
                        "\u{1F618}",
                        "\u{1F60D}",
                        "\u{263A}",
                        "\u{1F61A}",
                    ];

                    $weeb = [
                        "ヽ(♡‿♡)ノ",
                        "♡ (￣З￣)",
                        "(❤ω❤)",
                        "Σ>―(〃°ω°〃)♡→",
                        "Σ>―(〃°ω°〃)♡→",
                        "(´• ω •`) ♡",
                        "(´,,•ω•,,)♡",
                    ];

                    $chance = false;

                    if($weebMode)
                    {
                        $chance = self::chance(4000);
                    }
                    else
                    {
                        $chance = self::chance(2500);
                    }

                    if($chance)
                    {
                        if($weebMode)
                            return $weeb[array_rand($weeb)];
                        return $emojis[array_rand($emojis)];
                    }

                    break;

                case EmotionType::Neutral:
                    $emojis = [
                        "\u{1F610}",
                        "\u{1F928}",
                        "\u{1F642}",
                        "\u{1F9D0}",
                        "\u{1F927}",
                    ];

                    $weeb = [
                        "(*・ω・)ﾉ",
                        "(づ￣ ³￣)づ",
                        "(つ≧▽≦)つ",
                        "(つ✧ω✧)つ",
                        "(^人<)〜☆",
                        "┬┴┬┴┤･ω･)ﾉ",
                        "┬┴┬┴┤( ͡° ͜ʖ├┬┴┬┴",
                        "ε===(っ≧ω≦)っ",
                        "(=^ ◡ ^=)",
                        "(∩ᄑ_ᄑ)⊃━☆ﾟ*･｡*･:≡( ε:)",
                    ];

                    $chance = false;

                    if($weebMode)
                    {
                        $chance = self::chance(4000);
                    }
                    else
                    {
                        $chance = self::chance(300);
                    }

                    if($chance)
                    {
                        if($weebMode)
                            return $weeb[array_rand($weeb)];
                        return $emojis[array_rand($emojis)];
                    }

                    break;
            }

            return null;
        }

        public function owofiy(string $input)
        {
            // Make it lowercase
            $input = strtolower($input);

            // Fuck up the commas and periods
            $commas = [",", ",,", ",,,", ",,,,", ",,,,,",];
            $periods = ["..", ".,.,", "..,..,", ",,", ",,,,", "...", ".....", ",.."];
            $exclamations = ["!", "!!", "11", "!111!", "111!1", "!!111!11", "!!!!!", "!11!11"];
            $questions = ["?", "??", "?//?//d", "?????", "?!?!?", "??!?!?!??!"];
            $input = str_ireplace(",", $commas[array_rand($commas)], $input);
            $input = str_ireplace(".", $periods[array_rand($periods)], $input);
            $input = str_ireplace(".", $questions[array_rand($questions)], $input);

            // Fuck up the ascii
            $smiles = ["( ´ ω ` )", "(≧◡≦)", "(╯✧▽✧)╯"];
            $sadness = ["｡ﾟ(TヮT)ﾟ｡", "o(TヘTo)", "o(〒﹏〒)o", "(╯︵╰,)"];
            $input = str_ireplace(":(", $sadness[array_rand($sadness)], $input);
            $input = str_ireplace(":)", $smiles[array_rand($smiles)], $input);

            // Fuck up the words
            $brothers = ["onichan", "onichann", "oni-chan", "oniiichannnn"];
            $sisters = ["onee-chan", "onneechann", "onechan", "oneeechaaannnnn"];
            $dad = ["otuo-san", "outosann", "otuosaaann", "otuosaaannn"];
            $input = str_ireplace("brother", $brothers[array_rand($brothers)], $input);
            $input = str_ireplace("you", "senpai", $input);
            $input = str_ireplace("love", "wanna fuk", $input);
            $input = str_ireplace("brown", "the color of shit", $input);
            $input = str_ireplace("red", "blood color", $input);
            $input = str_ireplace("anime", "the purpose to my life", $input);
            $input = str_ireplace("mother", "gay", $input);
            $input = str_ireplace("sister", $sisters[array_rand($sisters)], $input);
            $input = str_ireplace("dad", $dad[array_rand($dad)], $input);
            $input = str_ireplace("awoooooo", "bakaaaaaa", $input);
            $input = str_ireplace("awooooo", "bakaaaaa", $input);
            $input = str_ireplace("awoooo", "bakaaaa", $input);
            $input = str_ireplace("awooo", "bakaaa", $input);
            $input = str_ireplace("awoo", "bakaa", $input);
            $input = str_ireplace("awo", "baka", $input);
            $input = str_ireplace("idiot", "baka", $input);

            // Fuck up the spelling
            $input = str_ireplace("ove", "uv", $input);
            $input = str_ireplace("l", "w", $input);
            $input = str_ireplace("r", "w", $input);

            return $input;
        }
    }
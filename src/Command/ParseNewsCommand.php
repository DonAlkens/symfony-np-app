<?php

namespace App\Command;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;

#[AsCommand(
    name: 'parse:news',
    description: 'Add a short description for your command',
)]
class ParseNewsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED, 'Url of the news resource')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = $input->getArgument('url');

        if ($url) {
            $io->note(sprintf('Parsing resource: %s', $url));

            $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
            $channel = $connection->channel();

            $channel->queue_declare('parse_news_queue', false, true, false, false);

            $message = new AMQPMessage($url);
            $channel->basic_publish($message, '', 'parse_news_queue');

            $io->success('New parsing request sent to que');
        }

        return Command::SUCCESS;
    }
}

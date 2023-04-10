<?php

namespace App\Command;

use App\Entity\News;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

#[AsCommand(
    name: 'parse:news-consumer',
    description: 'Handles news parsing for rabbitmq',
)]
class ParseNewsConsumerCommand extends Command
{
    private $entityManager;

    private $repository;

    public  function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->repository = $this->entityManager->getRepository(News::class);
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');

        $channel = $connection->channel();

        $channel->queue_declare('parse_news_queue', false, true, false, false);

        $callback = function ($msg) use ($output) {
            $url = $msg->body;

            $client = new Client(['verify' => false]);
            $response = $client->request('GET', $url);

            $html = $response->getBody()->getContents();

            $batchSize = 20;
            $i = 0;

            $crawler = new Crawler($html);
            $articles = $crawler->filter('.lenta-item');

            foreach ($articles as $article) {

                $flush = (($i % $batchSize) == 0) ? true : false;

                $title = $article->getElementsByTagName('h2')[0];
                $description = $article->getElementsByTagName('p')[0];
                $link = $article->getElementsByTagName('a')[1];
                $picture = $article->getElementsByTagName('img')[1];


                if ($title && $description && $link) {

                    $news = $this->repository->findOneBy(['title' => $title?->nodeValue]);

                    if ($news) {
                        $news->setDescription($description?->nodeValue);
                        $news->setLink($link?->getAttribute('href'));
                        $news->setPicture($picture?->getAttribute('src'));
                        $news->setUpdatedAt(new \DateTimeImmutable('now'));

                        $this->repository->save($news);
                    } else {
                        $newsInstance = new News($title?->nodeValue, $description?->nodeValue, $link?->getAttribute('href'), $picture?->getAttribute('src'));
                        $this->entityManager->persist($newsInstance);
                    }


                    if ($flush) {
                        $this->entityManager->flush();
                    }

                    $i++;
                }
            };


            $output->writeln('News parsed successfully.');
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
        };

        $channel->basic_qos(null, 1, null);
        $channel->basic_consume('parse_news_queue', '', false, false, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        return Command::SUCCESS;
    }
}

<?php

namespace App\Controller;

use App\Repository\NewsRepository;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class NewsController extends AbstractController
{
    #[Route('/news', name: 'app_news')]
    public function index(NewsRepository $newsRepository): Response
    {
        $qb = $newsRepository->getNewsPaginationQueryBuilder();
        $adapter = new QueryAdapter($qb);

        $pager = new Pagerfanta($adapter);
        $pager->setMaxPerPage(10);

        return $this->render('news/index.html.twig', [
            'news' => $pager,
        ]);
    }
}

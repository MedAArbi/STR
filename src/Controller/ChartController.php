<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use App\Repository\UserRepository;

class ChartController extends AbstractController
{
    public function index(UserRepository $userRepository, ChartBuilderInterface $chartBuilder)
{
    $data = $userRepository->countUsersByPhoneNumberPrefix();

    $chart = $chartBuilder->createChart(Chart::TYPE_BAR);
    $chart->setData([
        'labels' => array_keys($data),
        'datasets' => [
            [
                'label' => 'Number of Users',
                'backgroundColor' => ['#FF6384', '#36A2EB', '#FFCE56'],
                'data' => array_values($data),
            ],
        ],
    ]);

    return $this->render('back/chart.html.twig', [
        'chart' => $chart,
        'data' => $data, // Pass the data variable to the Twig template
    ]);
}

}

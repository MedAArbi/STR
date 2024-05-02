<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Roles;
use Symfony\Component\HttpFoundation\JsonResponse;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface;

class BackController extends AbstractController
{
    private EntityManagerInterface $entityManager; // Define the property

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager; // Initialize the property
    }
    #[Route('/', name: 'app_back_index', methods: ['GET', 'POST'])]
public function index(Request $request, UserRepository $userRepository, PaginatorInterface $paginator): Response
{
    $searchKeyword = $request->query->get('searchKeyword');
    $page = $request->query->getInt('page', 1); // Define the current page number

    if ($searchKeyword) {
        $usersQuery = $userRepository->searchUsersQuery($searchKeyword); // Assuming you have a method for fetching a query for user search
    } else {
        $usersQuery = $userRepository->findAll(); // Fetch all users
    }

    // Paginate the results
    $users = $paginator->paginate(
        $usersQuery,  // Query to paginate
        $page,   // Current page number
        5        // Number of items per page
    );

    if ($request->isXmlHttpRequest()) {
        // If the request is an AJAX request, return a JSON response with the HTML content
        $html = $this->renderView('back/users_table.html.twig', [
            'users' => $users,
        ]);

        return new JsonResponse([
            'html' => $html,
        ]);
    }

    // If the request is not AJAX, return the normal Twig view
    return $this->render('back/index.html.twig', [
        'users' => $users,
    ]);
}


    #[Route('/new', name: 'app_back_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $roleRepository = $entityManager->getRepository(Roles::class);
            $role = $roleRepository->findOneBy(['roleName' => 'admin']);
    
            // Si le rôle n'existe pas, créez-le
            if (!$role) {
                $role = new Roles();
                $role->setRoleName('admin');
                $entityManager->persist($role);
                // Notez que le flush n'est pas encore appelé ici
            }
    
            // Associez le rôle à l'utilisateur
            $user->setRole($role);
    
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_back_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('back/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_back_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('back/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_back_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_back_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('back/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_back_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_back_index', [], Response::HTTP_SEE_OTHER);
    }
   
    #[Route("/download-form-data/{id}", name:"download_form_data")]
    public function downloadFormData(ManagerRegistry $doctrine, int $id): Response
    {
        $userRepository = $doctrine->getRepository(User::class);
        $user = $userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException('No user found for id '.$id);
        }

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($pdfOptions);
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => FALSE,
                'verify_peer_name' => FALSE,
                'allow_self_signed' => TRUE
            ]
        ]);
        $dompdf->setHttpContext($context);

        $html = $this->renderView('back/pdf.html.twig', [
            'user' => $user
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output();
        $response = new Response($pdfContent);

        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'Donnees-Utilisateur-' . $user->getId() . '.pdf'
        );
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }
    #[Route('/charts', name: 'chart_route', methods: ['GET'])]
    public function chart_index(UserRepository $userRepository): Response
    {
        $data = $userRepository->countUsersByPhoneNumberPrefix();

        return $this->render('back/chart.html.twig', [
            'data' => $data,
        ]);
    }

}

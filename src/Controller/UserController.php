<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Form\UserType;
use App\Entity\Roles;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use App\Form\EmailPassForgottenType;
use App\Form\PassType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Form\ResetPasswordType;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\Persistence\ManagerRegistry;
use Twilio\Rest\Client;

use Symfony\Component\Mime\Email;
use Symfony\Component\HttpClient\HttpClient;

#[Route('/security')]

class UserController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route("/login", name: "app_login", methods: ["GET", "POST"])]
    public function login(Request $request): Response
    {
       
        // Récupérer les identifiants de l'utilisateur
        $mail = $request->request->get('mail');
        $mdp = $request->request->get('mdp');

        // Utiliser l'EntityManager pour accéder au référentiel User
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['mail' => $mail]);

        // Comparer les identifiants de l'utilisateur
        if ($user && $user->getMdp() === $mdp) {
            // Rediriger l'utilisateur en fonction de son rôle
            $role = $user->getRole()->getRoleName();
            if ($role === 'admin') {
                return $this->redirectToRoute('app_back_index');
            } else {
                return $this->redirectToRoute('app_scooter_booking');
            }
        } else {
            // Identifiants invalides, afficher un message d'erreur
            $this->addFlash('error', 'Invalid credentials. Please try again.');
            return $this->render('security/login.html.twig');
        }
    }

    #[Route("/register", name: "app_register", methods: ["GET", "POST"])]
    public function register(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $roleUtilisateur = $entityManager->getRepository(Roles::class)->findOneBy(['roleName' => 'utilisateur']);
    
            if ($roleUtilisateur) {
                $user->setRole($roleUtilisateur);
    
                $entityManager->persist($user);
                $entityManager->flush();
    
                // Send SMS to the registered user
                $this->sendWelcomeSms($user->getnumTel());
    
                return $this->redirectToRoute('app_home');
            } else {
                $this->addFlash('error', 'Le rôle "utilisateur" n\'a pas été trouvé.');
                return $this->redirectToRoute('app_home');
            }
        }
    
        return $this->render('security/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    
    private function sendWelcomeSms(string $toNumber): void
    {
        // Ajouter le préfixe international "+216"
        $toNumber = '+216' . $toNumber;
    
        $twilioSid = 'AC82b205abd90eba0ecaf975b8a2323271';
        $twilioToken = 'c3dd6081f435b73647b509db65042869';
        $client = new Client($twilioSid, $twilioToken);
    
        $fromNumber = '+12512501928'; // Votre numéro de téléphone Twilio
        $messageBody = 'Bienvenue sur notre plateforme ! Vous avez été inscrit avec succès.';
    
        $client->messages->create(
            $toNumber,
            [
                'from' => $fromNumber,
                'body' => $messageBody
            ]
        );
    }
    #[Route('/forgot-password', name: 'forgot_password')]
public function forgotPassword(
    Request $request,
    MailerInterface $mailer,
    SessionInterface $session
): Response
{
    $error = '';

    if ($request->isMethod('POST')) {
        $email = $request->request->get('email');

        // Valider l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format d\'email invalide';
        } else {
            // Générer un code aléatoire
            $code = mt_rand(100000, 999999);

            // Envoyer le code par email
            $message = (new Email())
                ->from('esprisco1995@outlook.com')
                ->to($email)
                ->subject('Code de réinitialisation du mot de passe')
                ->html("<p>Votre code de réinitialisation du mot de passe est : $code</p>");

            $mailer->send($message);

            // Enregistrez le code de vérification et l'email dans la session
            $session->set('reset_password_email', $email);
            $session->set('reset_password_code', $code);

            // Redirigez l'utilisateur vers la page de réinitialisation du mot de passe
            return $this->redirectToRoute('reset_password');
        }
    }

    return $this->render('security/passforgotten.html.twig', [
        'error' => $error,
    ]);
}


#[Route('/reset-passwordPage', name: 'reset_password_page')]
public function resetPage(){
    return $this->render('security/reset_password.html.twig',[
        
    ]);
}



#[Route('/reset-password', name: 'reset_password')]
public function resetPassword(
    Request $request,
    ManagerRegistry $doctrine,
    SessionInterface $session
): Response
{
    $error = '';

    // Récupérez l'email et le code de la session
    $mail = $session->get('reset_password_email');
    $code = $session->get('reset_password_code');

    if ($request->isMethod('POST')) {
        $enteredCode = $request->request->get('code');
        $password = $request->request->get('password');

        // Vérifiez si le code entré correspond à celui enregistré dans la session
        if ($enteredCode != $code) {
            $error = 'Code de vérification invalide';
        } else {
            // Récupérez l'utilisateur à partir de l'email enregistré dans la session
            $user = $doctrine->getRepository(User::class)->findOneBy(['mail' => $mail]);

            if (!$user) {
                $error = 'Utilisateur non trouvé';
            } else {
                // Mettez à jour le mot de passe de l'utilisateur
                $user->setMdp($password);
                $entityManager = $doctrine->getManager();
                $entityManager->flush();

                // Redirigez l'utilisateur vers la page de connexion
                return $this->redirectToRoute('app_login');
            }
        }
    }

    return $this->render('security/reset_password.html.twig', [
        'error' => $error,
    ]);
}
    /* #[Route('/sendmailer', name: 'send_mail')]
    public function mailer(Request $request, UserRepository $userRepo, MailerInterface $mailer, TokenGeneratorInterface $tokenGenerator): Response
    {
        $form = $this->createForm(EmailPassForgottenType::class);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $donnees = $form->get('mail')->getData();
            $user = $userRepo->findOneByEmail($donnees);
    
            if (!$user) {
                $this->addFlash('danger', 'Cette adresse n\'existe pas');
                return $this->render('security/passforgotten.html.twig', ['form' => $form->createView()]);
            }
    
            $token = $tokenGenerator->generateToken();
            try {
                $user->setResetToken($token);
                $entityManager = $this->entityManager;
                $entityManager->persist($user);
                $entityManager->flush();
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Une erreur est survenue : ' . $e->getMessage());
                return $this->render('security/passforgotten.html.twig', ['form' => $form->createView()]);
            }
    
            $url = $this->generateUrl('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
            $email = (new TemplatedEmail())
                ->from('esprisco1995@outlook.com')
                ->to($user->getMail())
                ->subject('Réinitialisation de votre mot de passe')
                ->htmlTemplate('security/reset_password.html.twig')
                ->context([
                    'resetUrl' => $url,
                    // Autres variables que vous souhaitez utiliser dans le template Twig
                ]);
    
            $mailer->send($email);
            $this->addFlash('message', 'Un e-mail de réinitialisation de mot de passe vous a été envoyé');
            return $this->redirectToRoute('app_login');
        }
    
        return $this->render('security/passforgotten.html.twig', ['form' => $form->createView()]);
    }
    
 
#[Route("/reset_pass/{token}", name: "app_reset_password")]
public function resetPass(Request $request, string $token, UserRepository $userRepo): Response
{
    if ($this->getUser()) {
        return $this->redirectToRoute('home');
    }

    $user = $userRepo->findOneByResetToken($token);

    if (!$user) {
        $this->addFlash('danger', 'Token Invalide');
        return $this->redirectToRoute('user_login');
    }

    $form = $this->createForm(ResetPasswordType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $user->setResetToken(null);
        // Ici, nous attribuons directement le mot de passe en clair
        // Encore une fois, cela n'est pas sécurisé et ne devrait être utilisé que pour des tests
        $user->setPassword($form->get('plainPassword')->getData());

        $entityManager = $this->entityManager;
        $entityManager->persist($user);
        $entityManager->flush();

        $this->addFlash('success', 'Mot de passe mis à jour avec succès');
        return $this->redirectToRoute('app_login');
    }

   
return $this->render('reset_password.html.twig', ['form' => $form->createView()]);

}
#[Route('/sendmailere', name: 'send_maile')]
public function mailerr(MailerInterface $mailer): Response
{
    $email = (new Email())
    ->from('esprisco1995@outlook.com') 
    ->to('medazizarbi1@gmail.com') 
    ->subject('Signed up ')
    ->text('Your inscription has been confirmed.');

    $mailer->send($email);

    return new Response(
        'Email sent successfully'
     );
}
    
}    
*/

}
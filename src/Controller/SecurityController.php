<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Form\ResetPasswordRequestFormType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
    
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

class SecurityController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();

        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
    
    #[Route('/access-denied', name: 'app_access_denied')]
    public function accessDenied(): Response
    {
        if ($this->isGranted('ROLE_BANNED')) {
            return $this->redirectToRoute('app_ban');
        }
        return $this->render('security/access_denied.html.twig', [
            'message' => 'Vous n’avez pas les autorisations nécessaires pour accéder à cette page.',
        ]);
    }
    #[Route('/ban', name: 'app_ban')]
    #[IsGranted('ROLE_BANNED')]
    public function banned(): Response
    {
        return $this->render('security/ban.html.twig', [
            'message' => 'a bozo tes ban',
        ]);
    }

    #[Route('/forgot', name: 'auth_forgot_get', methods: ['GET'])]
public function forgot_get(): Response
{
    return $this->render('security/forgot.html.twig');
}

#[Route('/forgot', name: 'auth_forgot_post', methods: ['POST'])]
public function forgot(
    Request $request,
    UserRepository $userRepository,
    MailerInterface $mailer,
    TokenGeneratorInterface $tokenGenerator,
): Response {
    $email = $request->get('_email');
    $user = $userRepository->findOneByEmail($email);

    if (!$user) {
        $this->addFlash('error', 'Utilisateur non trouvé');
        return $this->redirectToRoute('auth_forgot_get');
    }

    $token = $tokenGenerator->generateToken();
    $user->setResetToken($token);
    $this->entityManager->persist($user);
    $this->entityManager->flush();

    $url = $this->generateUrl('reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

    $emailMessage = (new Email())
        ->from('onboarding@resend.dev')
        ->to($user->getEmail())
        ->subject('Réinitialisation de votre mot de passe')
        ->html(sprintf(
            '<p>Bonjour %s,</p>
             <p>Pour réinitialiser votre mot de passe, cliquez sur le lien suivant :</p>
             <a href="%s">Réinitialiser votre mot de passe</a>',
            htmlspecialchars($user->getFirstname()),
            htmlspecialchars($url)
        ));
    $mailer->send($emailMessage);

    return $this->redirectToRoute('app_login');
}

#[Route('/forgot-password/{token}', name: 'reset_password')]
public function resetPassword(
    string $token,
    Request $request,
    UserRepository $userRepository,
    EntityManagerInterface $entityManager,
    UserPasswordHasherInterface $passwordHasher
): Response {
    $user = $userRepository->findOneByResetToken($token);

    $form = $this->createForm(ResetPasswordRequestFormType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $user->setResetToken(null);
        $user->setPassword(
            $passwordHasher->hashPassword(
                $user,
                $form->get('password')->getData()
            )
        );
        $entityManager->persist($user);
        $entityManager->flush();

        $this->addFlash('success', 'Mot de passe mis à jour avec succès.');
        return $this->redirectToRoute('app_login');
    }

    return $this->render('security/reset.html.twig', [
        'passForm' => $form->createView(),
    ]);
}    
}

<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @Route("/login", name="app_login", options={"permanent"=true, "keepRequestMethod"=true})
     */
    public function login(
        Request $request,
        LoggerInterface $logger,
        AuthenticationUtils $authenticationUtils
    ): Response {
        $redirectUri = null;
        if (isset($_SERVER['HTTP_COOKIE'])
            && preg_match('/sf_redirect=(.*)/', $_SERVER['HTTP_COOKIE'], $matches)
        ) {
            $redirectUri = $matches[1];
        }
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'redirect_uri' => $redirectUri
        ]);
    }

    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout()
    {
        throw new \Exception('This method can be blank - it will be intercepted by the logout key on your firewall');
    }

    /**
     * @Route("/forgot-password", name="app_forgot_password")
     */
    public function forgotPassword()
    {
        return $this->render('security/forgot-password.html.twig');
    }

    /**
     * @Route("/reset-password-request", name="app_reset_password_request", methods={"POST"})
     */
    public function resetPasswordRequest(
        EntityManagerInterface $entityManager,
        \Swift_Mailer $mailer,
        Request $request,
        ValidatorInterface $validator
    ) {
        $email = $request->get('email');

        $emailConstraint = new Email();
        $emailConstraint->message = $this->translator->trans('email_invalid', [], 'errors');

        $errorList = $validator->validate($email, $emailConstraint);

        if (0 < count($errorList)) {
            $errorMessage = $errorList[0]->getMessage();

            return $this->render(
                'security/forgot-password.html.twig',
                [
                    'error' => $errorMessage,
                    'email' => $email
                ]
            );
        } else {
            $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(['email' => $email]);

            // no message about email not exists because no email spoofing allowed!
            if ($user instanceof User) {
                $token = sha1(random_bytes(20));
                $user->setActivityToken($token);
                $user->setModified(new \DateTime());
                $user->setModifier($this->getUser() ?: $this->getDoctrine()->getRepository(User::class)->find(1));
                $entityManager->persist($user);
                $entityManager->flush();

                /** @TODO translate */
                $message = new \Swift_Message('Password request for https://retro.byte-artist.de');
                $message->setFrom('no-reply@byte-artist.de')
                    ->setTo($email)
                    ->setBcc('andreas.kempe@byte-artist.de')
                    ->setBody(
                        $this->renderView(
                            'emails/reset-password-request.html.twig',
                            [
                                'email' => $email,
                                'token' => $token
                            ]
                        ),
                        'text/html'
                    )
                    ->addPart(
                        $this->renderView(
                            'emails/reset-password-request.txt.twig',
                            [
                                'email' => $email,
                                'token' => $token
                            ]
                        ),
                        'text/plain'
                    );

                $mailer->send($message);
            }
        }

        return $this->render('security/reset-password-request.html.twig');
    }

    /**
     * @Route("/reset-password/{email}/{token}", name="app_reset_password", methods={"GET"})
     */
    public function resetPassword(string $email, string $token)
    {
        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(
            [
                'email' => $email,
                'activityToken' => $token
            ]
        );

        if (!$user instanceof User) {
            return $this->render('security/error.html.twig');
        }

        return $this->render(
            'security/reset-password.html.twig',
            [
                'email' => $email,
                'token' => $token
            ]
        );
    }

    /**
     * @Route("/reset-password", name="app_new_password", methods={"POST"})
     */
    public function newPassword(
        UserPasswordHasherInterface $encoder,
        EntityManagerInterface $entityManager,
        Request $request
    ) {
        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(
            [
                'email' => $request->get('email'),
                'activityToken' => $request->get('token')
            ]
        );

        if (!$user instanceof User) {
            return $this->render('security/error.html.twig');
        }

        $password = $encoder->hashPassword($user, $request->get('password'));
        $user->setPassword($password);
        $user->setModifier($this->getUser() ?: $this->getDoctrine()->getRepository(User::class)->find(1));
        $user->setModified(new \DateTime());
        $user->setActivityToken(null);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->render('security/new-password.html.twig');
    }
}

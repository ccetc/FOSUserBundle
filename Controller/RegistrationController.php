<?php

/*
 * This file is part of the FOSUserBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\UserBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use FOS\UserBundle\Model\UserInterface;

/**
 * Controller managing the registration
 *
 * @author Thibault Duplessis <thibault.duplessis@gmail.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
class RegistrationController extends ContainerAware
{
    public function registerAction()
    {
        if ($this->container->get('security.context')->isGranted('IS_AUTHENTICATED_FULLY')) {
            return new RedirectResponse($this->container->get('router')->generate('home'));
        }

        $baseLayout = $this->container->getParameter('fos_user.options.base_layout');
        $usePageHeader = $this->container->getParameter('fos_user.options.use_page_header');
        $flashName = $this->container->getParameter('fos_user.options.flash_name');
        $applicationTitle = $this->container->getParameter('fos_user.options.application_title');
        $adminEmail = $this->container->getParameter('fos_user.options.admin_email');
        $whyRegisterTemplate = $this->container->getParameter('fos_user.options.why_register_template');
        
        $form = $this->container->get('fos_user.registration.form');
        
        $formHandler = $this->container->get('fos_user.registration.form.handler');
        $confirmationEnabled = $this->container->getParameter('fos_user.registration.confirmation.enabled');
        $approvalEnabled = $this->container->getParameter('fos_user.registration.approval.enabled');
        
        $httpHost = $this->container->get('request')->getHttpHost();
        $baseUrl = $this->container->get('request')->getBaseUrl();
        $baseLink = $httpHost . $baseUrl;

        $process = $formHandler->process($confirmationEnabled, $approvalEnabled, $applicationTitle, $adminEmail);
        if($process)
        {
            $user = $form->getData();

            if($approvalEnabled)
            {
                $this->setFlash($flashName, 'Your account has been created.  Before you can log in an admin must verify your account.');
                $route = 'fos_user_security_login';
            }
            else if($confirmationEnabled)
            {
                $this->container->get('session')->set('fos_user_send_confirmation_email/email', $user->getEmail());
                $this->setFlash($flashName, 'Your account has been created.  An e-mail has been sent to '.$user->getEmail().'.  Follow the instructions in the e-mail to activate your account.');
                $route = 'fos_user_security_login';
            }
            else
            {
                $this->setFlash($flashName, 'Your account has been created.  You are now logged in.');
                $this->authenticateUser($user);
                $route = 'home';
            }


            $url = $this->container->get('router')->generate($route);

            return new RedirectResponse($url);
        }

        $templateParameters = array(
            'registrationForm' => $form->createView(),
            'theme' => $this->container->getParameter('fos_user.template.theme'),
            'baseLayout' => $baseLayout,
            'usePageHeader' => $usePageHeader,
            'whyRegisterTemplate' => $whyRegisterTemplate
        );

        if(class_exists('Sonata\AdminBundle\SonataAdminBundle')) {
            $adminPool = $this->container->get('sonata.admin.pool');
            $templateParameters['admin_pool'] = $adminPool;
        }        

        return $this->container->get('templating')->renderResponse('FOSUserBundle:Registration:register.html.' . $this->getEngine(), $templateParameters);
    }

    /**
     * Tell the user to check his email provider
     */
    public function checkEmailAction()
    {
        $email = $this->container->get('session')->get('fos_user_send_confirmation_email/email');
        $this->container->get('session')->remove('fos_user_send_confirmation_email/email');
        $user = $this->container->get('fos_user.user_manager')->findUserByEmail($email);

        if(null === $user)
        {
            throw new NotFoundHttpException(sprintf('The user with email "%s" does not exist', $email));
        }

        return $this->container->get('templating')->renderResponse('FOSUserBundle:Registration:checkEmail.html.' . $this->getEngine(), array(
            'user' => $user,
        ));
    }

    /**
     * Receive the confirmation token from user email provider, login the user
     */
    public function confirmAction($token)
    {
        $user = $this->container->get('fos_user.user_manager')->findUserByConfirmationToken($token);

        if(null === $user)
        {
            throw new NotFoundHttpException(sprintf('The user with confirmation token "%s" does not exist', $token));
        }

        $user->setConfirmationToken(null);
        $user->setEnabled(true);
        $user->setLastLogin(new \DateTime());

        $this->container->get('fos_user.user_manager')->updateUser($user);
        $this->authenticateUser($user);

        $flashName = $this->container->getParameter('fos_user.options.flash_name');

        $this->setFlash($flashName, 'Your account has been confirmed.  You are now logged in.');
        $url = $this->container->get('router')->generate('home');
        return new RedirectResponse($url);
    }

    /**
     * Tell the user his account is now confirmed
     */
    public function confirmedAction()
    {
        $user = $this->container->get('security.context')->getToken()->getUser();
        if(!is_object($user) || !$user instanceof UserInterface)
        {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        return $this->container->get('templating')->renderResponse('FOSUserBundle:Registration:confirmed.html.' . $this->getEngine(), array(
            'user' => $user,
        ));
    }

    /**
     * Authenticate a user with Symfony Security
     *
     * @param \FOS\UserBundle\Model\UserInterface $user
     */
    protected function authenticateUser(UserInterface $user)
    {
        try {
            $this->container->get('fos_user.user_checker')->checkPostAuth($user);
        } catch (AccountStatusException $e) {
            // Don't authenticate locked, disabled or expired users
            return;
        }

        $providerKey = $this->container->getParameter('fos_user.firewall_name');
        $token = new UsernamePasswordToken($user, null, $providerKey, $user->getRoles());

        $this->container->get('security.context')->setToken($token);
    }

    protected function setFlash($action, $value)
    {
        $this->container->get('session')->setFlash($action, $value);
    }

    protected function getEngine()
    {
        return $this->container->getParameter('fos_user.template.engine');
    }

}

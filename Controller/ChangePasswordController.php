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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Config\Definition\Exception\Exception;
use FOS\UserBundle\Model\UserInterface;

/**
 * Controller managing the password change
 *
 * @author Thibault Duplessis <thibault.duplessis@gmail.com>
 * @author Christophe Coevoet <stof@notk.org>
 */
class ChangePasswordController extends ContainerAware
{
    /**
     * Change user password
     */
    public function changePasswordAction()
    {
        $managePasswords = $this->container->getParameter('fos_user.options.manage_passwords');        

        if(!$managePasswords) {
            throw new Exception('You cannot change your password, passwords are managed elsewhere.');            
        }
        
        $baseLayout = $this->container->getParameter('fos_user.options.base_layout');
        $usePageHeader = $this->container->getParameter('fos_user.options.use_page_header');
        $flashName = $this->container->getParameter('fos_user.options.flash_name');
        
        $user = $this->container->get('security.context')->getToken()->getUser();
        if (!is_object($user) || !$user instanceof UserInterface) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        $form = $this->container->get('fos_user.change_password.form');
        $formHandler = $this->container->get('fos_user.change_password.form.handler');

        $process = $formHandler->process($user);
        if ($process) {
            $this->setFlash($flashName, 'Your password has been changed.');

            return new RedirectResponse($this->getRedirectionUrl($user));
        }

        $templateParameters = array(
                'changePasswordForm' => $form->createView(),
                'theme' => $this->container->getParameter('fos_user.template.theme'),
                'baseLayout' => $baseLayout,
                'usePageHeader' => $usePageHeader,
                'noPageHeaderBorder' => true
        );
        
        if(class_exists('Sonata\AdminBundle\SonataAdminBundle')) {
            $adminPool = $this->container->get('sonata.admin.pool');
            $templateParameters['admin_pool'] = $adminPool;
        }        
        
        return $this->container->get('templating')->renderResponse(
            'FOSUserBundle:ChangePassword:changePassword.html.'.$this->container->getParameter('fos_user.template.engine'),
            $templateParameters
        );
    }

    /**
     * Generate the redirection url when the resetting is completed.
     *
     * @param \FOS\UserBundle\Model\UserInterface $user
     *
     * @return string
     */
    protected function getRedirectionUrl(UserInterface $user)
    {
        return $this->container->get('router')->generate('fos_user_profile_show');
    }

    protected function setFlash($action, $value)
    {
        $this->container->get('session')->setFlash($action, $value);
    }
}

<?php

namespace App\Security\Voter;

use App\Entity\Board;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class BoardVoter extends Voter
{
    private $security;
    private $logger;

    public function __construct(LoggerInterface $logger, Security $security)
    {
        $this->security = $security;
        $this->logger = $logger;
    }

    protected function supports($attribute, $subject)
    {
        // replace with your own logic
        // https://symfony.com/doc/current/security/voters.html
        return in_array($attribute, ['edit', 'create', 'show', 'delete', 'archive'])
            && $subject instanceof Board;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        switch ($attribute) {
            case 'create':
                return $user instanceof UserInterface;
            case 'edit':
                if (!$user instanceof UserInterface) {
                    return false;
                }

                $results = $subject->getBoardMembers()->filter(
                    function($boardMember) use ($user) {
                        return $boardMember->getUser() === $user
                            && in_array('ROLE_ADMIN', $boardMember->getRoles());
                    }
                );

                return 0 < count($results);
            case 'show':
                if ("Demo Board" === $subject->getName()) {
                    return true;
                }

                $resultMembers = $subject->getBoardMembers()->filter(
                        function ($boardMember) use ($user) {
                            var_dump($boardMember);
                            return $boardMember->getUser() === $user;
                    }
                );

                $resultTeamMembers = $subject->getBoardMembers()->getBoard()->filter(
                        function ($boardMember) use ($user) {
                            var_dump($boardMember);
                            return $boardMember->getUser() === $user;
                    }
                );

                return 0 < count($resultMembers) || 0 < count($resultTeamMembers);
            case 'archive':
                $results = $subject->getBoardMembers()->filter(
                    function($boardMember) use ($user) {
                        return $boardMember->getUser() === $user
                            && in_array('ROLE_ADMIN', $boardMember->getRoles());
                    }
                );
                return 0 < count($results);
        }

        return false;
    }
}

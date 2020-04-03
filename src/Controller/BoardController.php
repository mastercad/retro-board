<?php

namespace App\Controller;

use App\Entity\BoardTeam;
use App\Entity\User;
use App\Entity\Team;
use App\Entity\TeamMember;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Board;
use App\Entity\BoardInvitation;
use App\Entity\BoardMember;
use App\Entity\BoardSubscriber;
use App\Entity\Column;
use App\Form\BoardType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BoardController extends AbstractController
{
    private $validator;
    private $mailer;
    private $entityManager;
    private $translator;

    public function __construct(\Swift_Mailer $mailer, ValidatorInterface $validator, EntityManagerInterface $entityManager, TranslatorInterface $translator)
    {
        $this->translator = $translator;
        $this->validator = $validator;
        $this->mailer = $mailer;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/boards", name="boards", methods={"GET"})
     */
    public function index(EntityManagerInterface $entityManager)
    {
        /*
        $boards = [];

        $boardMembers = $entityManager->getRepository(BoardMember::class)->findBy(['user' => $this->getUser()]);

        foreach ($boardMembers as $boardMember) {
            $boards[$boardMember->getBoard()->getName()] = $boardMember->getBoard();
        }

        $teamBoards = $this->getDoctrine()->getRepository(Team::class)->findAllTeamsForUser($this->getUser());

//        \Doctrine\Common\Util\Debug::dump($teamBoards->getBoardTeams());

        foreach ($teamBoards as $teamBoard) {
            foreach ($teamBoard as $currentBoards) {
                foreach ($currentBoards as $board) {
                    $boards[$board->getName()] = $board->getBoard();
                }
            }
        }

        if (empty($boards)) {
            $boards['Demo Board'] = $this->getDoctrine()->getRepository(Board::class)->findOneBy(['name' => 'Demo Board']);
        } else if (isset($boards['Demo Board'])) {
            unset ($boards['Demo Board']);
        }

        return $this->render(
            'index/index.html.twig',
            [
                'boards' => $boards,
            ]
        );
        */
        $boards = [];

        $boardMembers = $entityManager->getRepository(BoardMember::class)->findBy(['user' => $this->getUser()]);

        foreach ($boardMembers as $boardMember) {
            $boards[$boardMember->getBoard()->getName()] = $boardMember->getBoard();
        }
//        \Doctrine\Common\Util\Debug::dump($boardMembers);
/*
        $teams = $this->getDoctrine()->getRepository(Team::class)->findAllTeamsForUser($this->getUser());

        \Doctrine\Common\Util\Debug::dump($teams);

        foreach ($teams as $team) {
            \Doctrine\Common\Util\Debug::dump($team->getBoardTeams());
            foreach ($team->getBoardTeams() as $boardTeam) {
                $boards[$boardTeam->getBoard()->getName()] = $boardTeam->getBoard();
            }
        }
*/
        $boardTeams = $this->getDoctrine()->getRepository(BoardTeam::class)->findAllAvailableBoardsByUser($this->getUser());

        foreach ($boardTeams as $boardTeam) {
            $board = $this->getDoctrine()->getRepository(Board::class)->find($boardTeam['board']);
            $boards[$board->getName()] = $board;
        }

        if (empty($boards)) {
            $boards['Demo Board'] = $this->getDoctrine()->getRepository(Board::class)->findOneBy(['name' => 'Demo Board']);
        } else if (isset($boards['Demo Board'])) {
            unset ($boards['Demo Board']);
        }

        return $this->render(
            'index/index.html.twig',
            [
                'boards' => $boards,
            ]
        );
    }

    /**
     * @Route("/board/{id}", name="board_show", methods={"GET"}, requirements={"id"="\d+"})
     */
    public function showAction(int $id)
    {
//        $board = $this->getDoctrine()->getRepository(Board::class)->findActive($id)[0];
        $board = $this->getDoctrine()->getRepository(Board::class)->find($id);

        if (!$board) {
            throw $this->createNotFoundException($this->translator->trans('board_not_found', ['id' => $id], 'errors'));
        }

        $this->denyAccessUnlessGranted('show', $board);

        return $this->render(
            'board/show.html.twig',
            [
                'board' => $board,
                'archived' => 0
            ]
        );
    }

    /**
     * @Route("/board/archive/{id}", name="board_show_archive", methods={"GET"}, requirements={"id"="\d+"})
     */
    public function showArchiveAction(int $id)
    {
//        $board = $this->getDoctrine()->getRepository(Board::class)->findActive($id)[0];
        $board = $this->getDoctrine()->getRepository(Board::class)->find($id);

        if (!$board) {
            throw $this->createNotFoundException($this->translator->trans('board_not_found', ['id' => $id], 'errors'));
        }

        $this->denyAccessUnlessGranted('show', $board);

        return $this->render(
            'board/show.html.twig',
            [
                'board' => $board,
                'archived' => 1
            ]
        );
    }

    /**
     * @Route("/board/create", name="board_create", methods={"GET"})
     */
    public function createAction()
    {
        $form = $this->createForm(BoardType::class);
        $board = new Board();

        $this->denyAccessUnlessGranted('create', $board);

        $knownTeams = $this->collectAllKnownTeams();
        $knownMembers = $this->collectAllKnownMembers();

        $boardTeams = $this->getDoctrine()->getRepository(BoardTeam::class)->findBy(['board' => $board->getId()]);

        $form = $this->createForm(BoardType::class, $board, ['action' => $this->generateUrl('board_save')]);

        return $this->render(
            'board/create.html.twig',
            [
                'form' => $form->createView(),
                'board' => $board,
                'boardTeams' => $boardTeams,
                'knownTeams' => $knownTeams,
                'knownMembers' => $knownMembers
            ]
        );
    }

    /**
     * @Route("/board/create/{id}", name="board_edit", methods={"GET"}, requirements={"id"="\d+"})
     */
    public function editAction(int $id)
    {
        $board = $this->getDoctrine()->getRepository(Board::class)->find($id);

        if (!$board) {
            throw $this->createNotFoundException($this->translator->trans('board_not_found', ['id' => $id], 'errors'));
        }
        $this->denyAccessUnlessGranted('edit', $board);

        $knownTeams = $this->collectAllKnownTeams();
        $knownMembers = $this->collectAllKnownMembers();

        $boardTeams = $this->getDoctrine()->getRepository(BoardTeam::class)->findBy(['board' => $board->getId()]);

        $form = $this->createForm(BoardType::class, $board, ['action' => $this->generateUrl('board_save')]);

        return $this->render(
            'board/create.html.twig',
            [
                'form' => $form->createView(),
                'board' => $board,
                'boardTeams' => $boardTeams,
                'knownTeams' => $knownTeams,
                'knownMembers' => $knownMembers
            ]
        );
    }

    /**
     * @Route("/board/create", name="board_save", methods={"PUT", "POST"})
     */
    public function saveAction(Request $request) {
        $boardRequestData = $request->request->get('board');
        $id = (int)$boardRequestData['id'];

        /** Board $board */
        $board = null;
        $boardOwner = null;
        $errors = [];
        $success = false;

        if (0 < $id) {
            $board = $this->getDoctrine()->getRepository(Board::class)->find($id);
            $boardOwner = $this->getDoctrine()->getRepository(BoardMember::class)->findOneBy(['user' => $this->getUser(), 'board' => $board]);
            $board->setModifier($this->getUser());
            $board->setModified(new \DateTime());

            $this->denyAccessUnlessGranted('edit', $board);
        } else {
            $board = new Board();
            $board->setCreator($this->getUser());
            $board->setCreated(new \DateTime());

            $this->denyAccessUnlessGranted('create', $board);
        }

        $board->setName($boardRequestData['name']);

        if (!$boardOwner instanceof BoardMember) {
            $boardOwner = new BoardMember();
            $boardOwner->setBoard($board);
            $boardOwner->setRoles(['ROLE_ADMIN']);
            $boardOwner->setUser($this->getUser());
            $boardOwner->setCreator($this->getUser());
            $boardOwner->setCreated(new \DateTime());
        }

        $boardMemberSuccess = $this->manageBoardMember($request, $board);
        $boardInvitationsSuccess = $this->manageBoardInvitations($request, $board);
        $boardTeamsSuccess = $this->manageBoardTeams($request, $board);
        $boardColumnsSuccess = $this->manageBoardColumns($request, $board);

//        $boardRequestData = $request->request->get('board');

        if (!$boardMemberSuccess
            || !$boardInvitationsSuccess
            || !$boardTeamsSuccess
            || !$boardColumnsSuccess
        ) {
            if (isset($boardRequestData['members'])
                && is_array($boardRequestData['members'])
            ) {
                foreach ($boardRequestData['members'] as &$member) {
                    if ($member['boardMemberId'] instanceof BoardMember) {
                        $member['boardMemberId'] = $member['boardMemberId']->getId();
                    }
                }
            }

            if (isset($boardRequestData['teams'])
                && is_array($boardRequestData['teams'])
            ) {
                foreach ($boardRequestData['teams'] as &$team) {
                    if ($team['boardTeamId'] instanceof BoardTeam) {
                        $team['boardTeamId'] = $team['boardTeamId']->getId();
                    }
                }
            }

            if (isset($boardRequestData['columns'])
                && is_array($boardRequestData['columns'])
            ) {
                foreach ($boardRequestData['columns'] as &$column) {
                    if ($column['columnId'] instanceof Column) {
                        $column['columnId'] = $column['columnId']->getId();
                    }
                }
            }

            if (isset($boardRequestData['invitations'])
                && is_array($boardRequestData['invitations'])
            ) {
                foreach ($boardRequestData['invitations'] as &$invitation) {
                    if ($invitation['boardInvitationId'] instanceof BoardInvitation) {
                        $this->sendInvitationEmail($board, $invitation['boardInvitationId']);

                        $invitation['id'] = $invitation['boardInvitationId']->getId();
                        $invitation['token'] = $invitation['boardInvitationId']->getToken();
                        $invitation['boardInvitationId'] = $invitation['boardInvitationId']->getId();
                    }
                }
            }
            return new JsonResponse(['success' => false, 'data' => $boardRequestData]);
        }
/*
        $members = [];
        if (isset($boardRequestData['members'])) {
            $members = $boardRequestData['members'];
            unset($boardRequestData['members']);
        }

        $teams = [];
        if (isset($boardRequestData['teams'])) {
            $teams = $boardRequestData['teams'];
            unset($boardRequestData['teams']);
        }

        $invitations = [];
        if (isset($boardRequestData['invitations'])) {
            $invitations = $boardRequestData['invitations'];
            unset($boardRequestData['invitations']);
        }

        $columns = [];
        if (isset($boardRequestData['columns'])) {
            $columns = $boardRequestData['columns'];
            unset($boardRequestData['columns']);
        }
        $request->request->set('board', $boardRequestData);
*/

//        $form = $this->createForm(BoardType::class, $board, ['creator' => $this->getUser()]);
//        $form->handleRequest($request);

        $errors = $this->validator->validate($board);

        if (1 || 0 < count($errors)) {
            try {
                /** @var ArrayCollection */
//                $columns = $board->getColumns();

//                if ($columns->isEmpty()) {
//                    $errors = [['content' => $this->translator->trans('error_one_column_needed_in_board', [], 'errors')]];
//                } else {
                    $this->entityManager->persist($board);
                    $this->entityManager->persist($boardOwner);
                    $this->entityManager->flush();

                    $success = true;

                    if (isset($boardRequestData['columns'])
                        && is_array($boardRequestData['columns'])
                    ) {
                        foreach ($boardRequestData['columns'] as &$column) {
                            if ($column['id'] instanceof Column) {
                                $column['id'] = $column['id']->getId();
                            }
                        }
                    }

                    $boardRequestData['id'] = $board->getId();

                    if (isset($boardRequestData['members'])
                        && is_array($boardRequestData['members'])
                    ) {
                        foreach ($boardRequestData['members'] as &$member) {
                            if ($member['boardMemberId'] instanceof BoardMember) {
                                $member['boardMemberId'] = $member['boardMemberId']->getId();
                            }
                        }
                    }

                    if (isset($boardRequestData['teams'])
                        && is_array($boardRequestData['teams'])
                    ) {
                        foreach ($boardRequestData['teams'] as &$team) {
                            if ($team['boardTeamId'] instanceof BoardTeam) {
                                $team['boardTeamId'] = $team['boardTeamId']->getId();
                            }
                        }
                    }

                    if (isset($boardRequestData['invitations'])
                        && is_array($boardRequestData['invitations'])
                    ) {
                        foreach ($boardRequestData['invitations'] as &$invitation) {
                            if ($invitation['boardInvitationId'] instanceof BoardInvitation) {
                                $this->sendInvitationEmail($invitation['boardInvitationId']);

                                $invitation['id'] = $invitation['boardInvitationId']->getId();
                                $invitation['token'] = $invitation['boardInvitationId']->getToken();
                                $invitation['boardInvitationId'] = $invitation['boardInvitationId']->getId();
                            }
                        }
                    }
//                }
            } catch (UniqueConstraintViolationException $exception) {
                $errors = [['content' => $this->translator->trans('one_or_more_columns_already_exists_in_board', [], 'errors')]];
            }
        } else {
//            $errors = $form->getErrors(true, false);
            var_dump("FORM INVALID!");
//            dump($form->getErrors());
//            dump($form['board']->getErrors());
            dump($errors);
        }

        return new JsonResponse([
            'success' => $success, 
            'id' => $board->getId(), 
            'data' => $boardRequestData,
            'content' => $success ? $this->translator->trans('board_saved', [], 'messages') : json_encode($errors)
//            'content' => $success ? $this->translator->trans('board_saved', [], 'messages') : (string) $errors
        ]);
    }

    /**
     * Manage given member for board membership.
     *
     * @param Request $request
     *
     * @return bool
     */
    private function manageBoardMember(Request $request, Board $board) : bool {

        $success = true;
        $boardRequestData = $request->request->get('board');
        if (!isset($boardRequestData['members'])) {
            return $success;
        }

        foreach ($boardRequestData['members'] as &$member) {
            $boardMember = null;
            $user = $this->getDoctrine()->getRepository(User::class)->find($member['userId']);
            if (empty($member['boardMemberId'])) {
                $boardMember = new BoardMember;
                $boardMember->setUser($user);
                $boardMember->setCreator($this->getUser());
                $boardMember->setCreated(new \DateTime());
                $boardMember->setBoard($board);
                $boardMember->setRoles($member['roles']);
                $member['boardMemberId'] = $boardMember;
            } else {
                $boardMember = $this->getDoctrine()->getRepository(BoardMember::class)->find($member['boardMemberId']);
                $boardMember->setRoles($member['roles']);
                $boardMember->setModifier($this->getUser());
                $boardMember->setModified(new \DateTime());
            }
            $this->entityManager->persist($boardMember);
        }

        $request->request->set('board', $boardRequestData);
        return $success;
    }

    /**
     * Manage given teams for board.
     *
     * @param Request $request
     *
     * @return bool
     */
    private function manageBoardTeams(Request $request, Board $board) : bool {

        $success = true;

        $boardRequestData = $request->request->get('board');
        if (!isset($boardRequestData['teams'])) {
            return $success;
        }

        foreach ($boardRequestData['teams'] as &$currentTeam) {
            $boardTeam = null;
            $team = $this->getDoctrine()->getRepository(Team::class)->find($currentTeam['teamId']); 
            if (empty($currentTeam['boardTeamId'])) {
                $boardTeam = new BoardTeam;
                $boardTeam->setTeam($team);
                $boardTeam->setBoard($board);
                $boardTeam->setCreator($this->getUser());
                $boardTeam->setCreated(new \DateTime());
                $currentTeam['boardTeamId'] = $boardTeam;
            } else {
                $boardTeam = $this->getDoctrine()->getRepository(BoardTeam::class)->find($currentTeam['boardTeamId']);
                $boardTeam->setModifier($this->getUser());
                $boardTeam->setModified(new \DateTime());
            }
            $this->entityManager->persist($boardTeam);
        }

        $request->request->set('board', $boardRequestData);
        return $success;
    }

    /**
     * Manage given invitations for board membership.
     *
     * @param Request $request
     *
     * @return bool
     */
    private function manageBoardInvitations(Request $request, Board $board) : bool {

        $success = true;

        $boardRequestData = $request->request->get('board');
        if (!isset($boardRequestData['invitations'])) {
            return $success;
        }

        foreach ($boardRequestData['invitations'] as &$invitation) {
            $result = $this->handleBoardInvitation($board, $invitation['email'], $invitation['boardInvitationId'], true);

            if ($result instanceof BoardInvitation) {
                $invitation['boardInvitationId'] = $result;
            } else {
                $success = false;
                $invitation['result'] = $result;
            }
        }

        $request->request->set('board', $boardRequestData);
        return $success;
    }

    /**
     * Manage given columns for board membership.
     *
     * @param Request $request
     *
     * @return bool
     */
    private function manageBoardColumns(Request $request, Board $board) : bool {

        $success = true;

        $boardRequestData = $request->request->get('board');
        if (!isset($boardRequestData['columns'])) {
            return $success;
        }

        foreach ($boardRequestData['columns'] as &$currentColumn) {
            $name = $currentColumn['name'];
            $priority = $currentColumn['priority'];

            if (empty($currentColumn['id'])) {
                $column = new Column;
                $column->setBoard($board);
                $currentColumn['id'] = $column;
            } else {
                $column = $this->getDoctrine()->getRepository(Column::class)->find($currentColumn['id']);
            }
            $column->setName($name);
            $column->setPriority($priority);

            $this->entityManager->persist($column);
        }

        $request->request->set('board', $boardRequestData);
        return $success;
    }

    /**
     * @Route("/board/invite", name="board_invite", methods={"POST"})
     *
     * @param \Swift_Mailer          $mailer
     * @param Request                $request
     * @param EntityManagerInterface $entityManager
     *
     * @return JsonResponse
     */
    public function inviteAction(Request $request)
    {
        $board = $this->getDoctrine()->getRepository(Board::class)->find($request->request->get('id'));

        $data = $this->handleBoardInvitation($board, $request->request->get('email'), $request->request->get('invitationId'));

        return new JsonResponse($data);
    }

    private function handleBoardInvitation($board, $email, $invitationId = null, $postProcessed = false) {

        $boardInvitation = new BoardInvitation();
        $token = sha1(random_bytes(20));
        $data = [];

        if (!empty($invitationId)) {
            $boardInvitation = $this->getDoctrine()->getRepository(BoardInvitation::class)->find($invitationId);
            $boardInvitation->setToken($token);
            $boardInvitation->setModifier($this->getUser());
            $boardInvitation->setModified(new \DateTime());

            $email = $boardInvitation->getEmail();
        } else {
            $emailConstraint = new Email();
            $emailConstraint->message = $this->translator->trans('email_invalid', [], 'errors');

            $errorList = $this->validator->validate(
                $email,
                $emailConstraint
            );

            if (0 < count($errorList)) {
//                throw new InvalidArgumentException("Email ".$email." invalid!");
                $data['code'] = 500;
                $data['content'] = $this->translator->trans('email_invalid', [], 'errors');
                $data['success'] = false;

                if (!empty($boardInvitation->getId())) {
                    $this->entityManager->remove($boardInvitation);

                    if (!$postProcessed) {
                        $this->entityManager->flush();
                    }
                }

                return $data;
            }

            $boardInvitation->setBoard($board);
            $boardInvitation->setEmail($email);
            $boardInvitation->setToken($token);
            $boardInvitation->setCreator($this->getUser());
            $boardInvitation->setCreated(new \DateTime());
        }

        if ($board->getId()) {
            $this->denyAccessUnlessGranted('create', $boardInvitation);
        }

        try {
            $this->entityManager->persist($boardInvitation);

            $data['code'] = 200;
            $data['success'] = true;
            $data['content'] = $this->translator->trans('success', [], 'messages');

            if (!$postProcessed) {
                $this->entityManager->flush();
                $data['id'] = $boardInvitation->getId();
                $data['token'] = $boardInvitation->getToken();
                $this->sendInvitationEmail($boardInvitation);
            }
        } catch (UniqueConstraintViolationException $exception) {
            $data['code'] = 500;
            $data['success'] = false;
            $data['content'] = $this->translator->trans('email_already_invited', [], 'errors');
            $data['id'] = $boardInvitation->getId();
            $data['token'] = $boardInvitation->getToken();
        }

        if ($postProcessed) {
            return $boardInvitation;
        }

        return $data;
    }

    /**
     * Undocumented function
     *
     * @param BoardInvitation $boardInvitation
     *
     * @return void
     */
    private function sendInvitationEmail(BoardInvitation $boardInvitation) {

            $message = new \Swift_Message('Invitation request board "'.$boardInvitation->getBoard()->getName().'" on https://retro.byte-artist.de');
            $message->setFrom('no-reply@byte-artist.de')
                ->setTo($boardInvitation->getEmail())
                ->setBcc('andreas.kempe@byte-artist.de')
                ->setBody(
                    $this->renderView(
                        'emails/invite-user.html.twig',
                        [
                            'email' => $boardInvitation->getEmail(),
                            'board' => $boardInvitation->getBoard(),
                            'token' => $boardInvitation->getToken()
                        ]
                    ),
                    'text/html'
                )
                ->addPart(
                    $this->renderView(
                        'emails/invite-user.txt.twig',
                        [
                            'email' => $boardInvitation->getEmail(),
                            'board' => $boardInvitation->getBoard(),
                            'token' => $boardInvitation->getToken()
                        ]
                    ),
                    'text/plain'
                );

            $this->mailer->send($message);

        return $this;
    }

    /**
     * @Route("/board/invitation/{id}", name="board_invite_delete", methods={"DELETE"})
     *
     * @param integer $id
     *
     * @return void
     */
    public function deleteInvitationAction(int $id)
    {
        $boardInvitation = $this->getDoctrine()->getRepository(BoardInvitation::class)->find($id);
        if (!$boardInvitation instanceof BoardInvitation) {
            return new JsonResponse(['success' => false, 'content' => $this->translator->trans('invitation_not_found', [], 'errors')]);
        }

        $this->denyAccessUnlessGranted('delete', $boardInvitation);

        $this->entityManager->remove($boardInvitation);
        $this->entityManager->flush();

        return new JsonResponse(
            [
                'success' => true,
                'content' => $this->translator->trans('invitation_removed', [], 'messages')
            ]
        );
    }

    /**
     * @Route("/board/{id}", name="board_delete", methods={"DELETE"})
     *
     * @param integer $id
     *
     * @return void
     */
    public function deleteAction(int $id)
    {
        $board = $this->getDoctrine()->getRepository(Board::class)->find($id);
        if (!$board instanceof Board) {
            return new JsonResponse(['success' => false, 'content' => $this->translator->trans('board_not_found', [], 'errors')]);
        }

        $this->denyAccessUnlessGranted('delete', $board);

        $this->entityManager->remove($board);
        $this->entityManager->flush();

        return new JsonResponse(
            [
                'success' => true,
                'content' => $this->translator->trans('board_removed', [], 'messages')
            ]
        );
    }

    /**
     * @Route("/board/member/{token}", name="board_member", methods={"GET"})
     *
     * @param Request                $request
     */
    public function memberAction(string $token)
    {
        $boardInvitation = $this->getDoctrine()->getRepository(BoardInvitation::class)->findOneBy(['token' => $token]);

        if (!$boardInvitation instanceof BoardInvitation) {
            throw $this->createNotFoundException($this->translator->trans('invitation_not_found', [], 'errors'));
        }

        $this->denyAccessUnlessGranted('accept', $boardInvitation);

        $boardMember = new BoardMember();
        $boardMember->setBoard($boardInvitation->getBoard());
        $boardMember->setUser($this->getUser());
        $boardMember->setCreator($this->getUser());
        $boardMember->setCreated(new \DateTime());
        $boardMember->setRoles(["ROLE_USER"]);

        $boardSubscriber = new BoardSubscriber();
        $boardSubscriber->setBoard($boardInvitation->getBoard());
        $boardSubscriber->setSubscriber($this->getUser());
        $boardSubscriber->setCreator($this->getUser());
        $boardSubscriber->setCreated(new \DateTime());

        try {
            $this->entityManager->persist($boardMember);
            $this->entityManager->persist($boardSubscriber);
            $this->entityManager->remove($boardInvitation);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            // dürfte eigentlich nicht passieren da invitations gelöscht werden, wenn die subscribtion vollständig ablief
            // ist im grunde egal, da der user schon member ist und daher kann weiter geleitet werden.
        }
        return $this->redirectToRoute("board_show", ['id' => $boardMember->getBoard()->getId()]);
    }

    /**
     * @Route("/board/team/{id}", name="board_team_delete", methods={"DELETE"})
     *
     * @param integer $id
     *
     * @return void
     */
    public function deleteTeamAction(int $id)
    {
        $boardTeam = $this->getDoctrine()->getRepository(BoardTeam::class)->find($id);

        if (!$boardTeam instanceof BoardTeam) {
            return new JsonResponse(['success' => false, 'content' => $this->translator->trans('team_not_found_for_board', [], 'errors')]);
        }

        $this->entityManager->remove($boardTeam);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'content' => $this->translator->trans('team_removed_from_board', [], 'messages')]);
    }

    /**
     * @Route("/board/member/{id}", name="board_member_delete", methods={"DELETE"})
     *
     * @param integer $id
     *
     * @return void
     */
    public function deleteMemberAction(int $id)
    {
        $boardMember = $this->getDoctrine()->getRepository(BoardMember::class)->find($id);

        if (!$boardMember instanceof BoardMember) {
            return new JsonResponse(['success' => false, 'content' => $this->translator->trans('member_not_found_for_board', [], 'errors')]);
        }

        $this->entityManager->remove($boardMember);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'content' => $this->translator->trans('member_removed_from_board', [], 'messages')]);
    }

    /**
     * @Route("/board/subscribe/{id}", name="board_subscribe", methods={"GET"})
     *
     * @param integer $id
     *
     * @return void
     */
    public function subscribeAction(int $id)
    {
        $board = $this->getDoctrine()->getRepository(Board::class)->find($id);
        $content = "";

        if (!$board instanceof Board) {
            return new JsonResponse(['success' => false, 'content' => $this->translator->trans('board_not_found', ['id' => $id], 'errors')]);
        }

        $boardSubscriber = $this->getDoctrine()->getRepository(BoardSubscriber::class)->findOneBy(
            [
                'board' => $board,
                'subscriber' => $this->getUser()
            ]
        );

        if (!$boardSubscriber instanceof BoardSubscriber) {
            $boardSubscriber = new BoardSubscriber();
            $boardSubscriber->setBoard($board);
            $boardSubscriber->setSubscriber($this->getUser());
            $boardSubscriber->setCreator($this->getUser());
            $boardSubscriber->setCreated(new \DateTime());

            $this->entityManager->persist($boardSubscriber);
            $content = $this->translator->trans('board_successfully_subscribed', [], 'messages');
        } else {
            $this->entityManager->remove($boardSubscriber);
            $content = $this->translator->trans('board_successfully_unsubscribed', [], 'messages');
        }

        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'content' => $content]);
    }

    /**
     * @Route("/board/member", name="board_add_member", methods={"POST"})
     *
     * @param Request $request
     *
     * @return void
     */
    public function addMemberAction(Request $request) {
        $data = [];
        $boardMember = new BoardMember();

        $board = $this->getDoctrine()->getRepository(Board::class)->find($request->request->get('boardId'));

        $this->denyAccessUnlessGranted('edit', $board);

        try {
            if (!empty($request->request->get('boardMemberId'))) {
                $boardMember = $this->getDoctrine()->getRepository(BoardInvitation::class)->find($request->request->get('boardMemberId'));
                $this->denyAccessUnlessGranted('edit', $boardMember);
                $boardMember->addRole($request->request->get('userRole'));
                $boardMember->setModifier($this->getUser());
                $boardMember->setModified(new \DateTime());
            } else {
                $this->denyAccessUnlessGranted('create', $boardMember);
                $member = $this->getDoctrine()->getRepository(User::class)->find($request->request->get('userId'));

                $boardMember->setBoard($board);
                $boardMember->setUser($member);
                $boardMember->setRoles([$request->request->get('userRole')]);
                $boardMember->setCreator($this->getUser());
                $boardMember->setCreated(new \DateTime());
            }

            $this->entityManager->persist($boardMember);
            $this->entityManager->flush();

            $data['code'] = 200;
            $data['success'] = true;
            $data['content'] = $this->translator->trans('success', [], 'messages');
            $data['id'] = $boardMember->getId();
        } catch (UniqueConstraintViolationException $exception) {
            $data['code'] = 500;
            $data['success'] = false;
            $data['content'] = $this->translator->trans('member_already_added', [], 'errors');
            $data['id'] = $boardMember->getId();
        }
        return new JsonResponse($data);
    }

    /**
     * @Route("/board/team", name="board_add_team", methods={"POST"})
     *
     * @param Request $request
     *
     * @return void
     */
    public function addTeamAction(Request $request) {
        $data = [];
        $boardTeam = new BoardTeam();

        $board = $this->getDoctrine()->getRepository(Board::class)->find($request->request->get('boardId'));
        $team = $this->getDoctrine()->getRepository(Team::class)->find($request->request->get('teamId'));

        $this->denyAccessUnlessGranted('edit', $board);

        try {
            $this->denyAccessUnlessGranted('create', $boardTeam);

            $boardTeam->setBoard($board);
            $boardTeam->setTeam($team);
            $boardTeam->setCreator($this->getUser());
            $boardTeam->setCreated(new \DateTime());

            $this->entityManager->persist($boardTeam);
            $this->entityManager->flush();

            $data['code'] = 200;
            $data['success'] = true;
            $data['content'] = $this->translator->trans('success', [], 'messages');
            $data['id'] = $boardTeam->getId();
        } catch (UniqueConstraintViolationException $exception) {
            $data['code'] = 500;
            $data['success'] = false;
            $data['content'] = $this->translator->trans('team_already_added', [], 'errors');
            $data['id'] = $boardTeam->getId();
        }
        return new JsonResponse($data);
    }

    private function collectAllKnownBoards() {
        /*
        $boards = [];
        // select all boards where i in board_members as user
        $boardIds = $this->getDoctrine()->getRepository(Board::class)->findAllKnownBoards($this->getUser());

        foreach ($boardIds as $boardId) {
            $boards[] = $this->getDoctrine()->getRepository(Board::class)->find($boardId['id']);
        }
        return $boards;
        */
        return $this->getDoctrine()->getRepository(Board::class)->findAllKnownBoards($this->getUser());
    }

    private function collectAllKnownTeams() {
        /*
        $teams = [];
        // select all boards where i in board_members as user
        $teamIds = $this->getDoctrine()->getRepository(Board::class)->findAllKnownTeams($this->getUser());

        foreach ($teamIds as $teamId) {
            $teams[] = $this->getDoctrine()->getRepository(Team::class)->find($teamId['id']);
        }
        return $teams;
        */
        return $this->getDoctrine()->getRepository(Board::class)->findAllKnownTeams($this->getUser());
    }

    private function collectAllKnownMembers() {
        /*
        $users = [];
        // select all boards where i in board_members as user
        $userIds = $this->getDoctrine()->getRepository(Board::class)->findAllKnownMembers($this->getUser());

        foreach ($userIds as $userId) {
            $users[] = $this->getDoctrine()->getRepository(Team::class)->find($userId['id']);
        }
        return $users;
        */
        return $this->getDoctrine()->getRepository(Board::class)->findAllKnownMembers($this->getUser());
    }
}

<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BoardTeam.
 *
 * @ORM\Table(
 *  name="board_teams",
 *  indexes={
 *      @ORM\Index(name="board_teams_team_fk", columns={"team"}),
 *      @ORM\Index(name="board_teams_board_fk", columns={"board"}),
 *      @ORM\Index(name="board_teams_modifier_fk", columns={"modifier"}),
 *      @ORM\Index(name="board_teams_creator_fk", columns={"creator"})
 *  }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\BoardTeamRepository"))
 */
class BoardTeam
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", length=11, columnDefinition="integer unsigned", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var Team
     *
     * @ORM\ManyToOne(targetEntity="Team", inversedBy="boardTeams")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="team", columnDefinition="integer unsigned", referencedColumnName="id", nullable=false)
     * })
     */
    private $team;

    /**
     * @var Board
     *
     * @ORM\ManyToOne(targetEntity="Board", inversedBy="boardTeams")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="board", columnDefinition="integer unsigned", referencedColumnName="id", nullable=false)
     * })
     */
    private $board;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="creator", columnDefinition="integer unsigned", referencedColumnName="id", nullable=false)
     * })
     */
    private $creator;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created", type="datetime", nullable=false)
     */
    private $created;

    /**
     * @var User
     *
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="modifier", columnDefinition="integer unsigned", referencedColumnName="id", nullable=true)
     * })
     */
    private $modifier;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="modified", type="datetime", nullable=true)
     */
    private $modified;

    /**
     * Get the value of id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id.
     *
     * @return self
     */
    public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of Team.
     *
     * @return Team
     */
    public function getTeam()
    {
        return $this->team;
    }

    /**
     * Set the value of team.
     *
     * @param Team $team
     *
     * @return self
     */
    public function setTeam(?Team $team)
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Get the value of board.
     *
     * @return Board
     */
    public function getBoard()
    {
        return $this->board;
    }

    /**
     * Set the value of board.
     *
     * @param Board $board
     *
     * @return self
     */
    public function setBoard(?Board $board)
    {
        $this->board = $board;

        return $this;
    }

    /**
     * Get the value of creator.
     *
     * @return User
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * Set the value of creator.
     *
     * @return self
     */
    public function setCreator(User $creator)
    {
        $this->creator = $creator;

        return $this;
    }

    /**
     * Get the value of created.
     *
     * @return \DateTime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set the value of created.
     *
     * @return self
     */
    public function setCreated(\DateTime $created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Get the value of modifier.
     *
     * @return User
     */
    public function getModifier()
    {
        return $this->modifier;
    }

    /**
     * Set the value of modifier.
     *
     * @return self
     */
    public function setModifier(User $modifier)
    {
        $this->modifier = $modifier;

        return $this;
    }

    /**
     * Get the value of modified.
     *
     * @return \DateTime|null
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Set the value of modified.
     *
     * @param \DateTime|null $modified
     *
     * @return self
     */
    public function setModified($modified)
    {
        $this->modified = $modified;

        return $this;
    }
}

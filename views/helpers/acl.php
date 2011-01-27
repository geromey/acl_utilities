<?php
/**
 * Acl Helper
 *
 * Acl view helper allowing to check the Acl from the views
 *
 * @package AclUtilities
 * @subpackage AclUtilities.views.helpers
 */
class AclHelper extends AppHelper
{
  public $helpers = array('Session', 'Html');

  /**
   *
   * foreign key of the aro Usually the User.id
   * @var integer
   */
  private $__foreignKey;

  /**
   *
   * Lists of actions allowed for annonymous users
   * @var array
   */
  private $__allowedActions;

  /**
   *
   * Acl Component used for checking the access
   * @var AclComponent
   */
  private $__acl;

  /**
   * List of current blocks
   * @var array
   */
  private $__blocks;

  /**
   *
   * Inits some variables
   */
  public function beforeRender()
  {
    parent::beforeRender();

    $this->__blocks = array();

    $this->__allowedActions = Configure::read('AclUtilities.allowedActions');

    $this->__foreignKey = $this->Session->read('Auth.User.id');

    // if not logged in, then no need for the Acl
    if (!$this->isLoggedin())
      return;

    App::import('Component', 'Acl');
    $this->__acl = new AclComponent();


  }

  /**
   *
   * Check if the url in param can be accessed by the current user
   * @param array $url
   */
  public function check($url)
  {
    $params = $this->params;
    unset($params['pass']);
    $url = array_merge($this->params,$url);

    $controller = ucfirst($url['controller']);
    $action = strtolower($url['action']);

    // check against the allowedActions
    if (isset($this->__allowedActions[$controller])
      && (in_array('*', $this->__allowedActions[$controller])
        || in_array($action, $this->__allowedActions[$controller])))
      return true;

    // if not logged in, then no need for the Acl
    if (!$this->isLoggedin())
      return false;

    // find the aco node
    $aco = 'controllers/' . $controller . '/' . $action;
    if (isset($url[0]))
      $aco .= '/' . $url[0];
    while(false === $this->__acl->Aco->node($aco))
    {
      $slashPos = strrpos($aco, '/');
      // If we reach the top level aco and no nodes have been found
      // then no access
      if (false === $slashPos)
        return false;
      $aco = substr($aco , 0, $slashPos);
    }
    $aro = array('model' => 'User', 'foreign_key' => $this->__foreignKey);
    return $this->__acl->check($aro, $aco);
  }

  /**
   *
   * call Html->link() with same params if the user has access to the link
   * can contains 'wrapper in $option which will wrap the link if displayed
   * @param string $title
   * @param array $url
   * @param array $options
   * @param string $confirmMessage
   */
  public function link($title, $url = null, $options = array(), $confirmMessage = false)
  {
    if (!$this->check($url))
      return '';

    // set all the block to true so they will get displayed
    foreach ($this->__blocks as $id =>$val)
    {
      $this->__blocks[$id] = true;
    }

    if (isset($options['wrapper']))
    {
      if (isset($this->Html->tags[$options['wrapper']]))
        $wrapper = $this->Html->tags[$options['wrapper']];
      else
        $wrapper = $options['wrapper'];

      unset($options['wrapper']);
    }
    else
      $wrapper = null;

    $link = $this->Html->link($title, $url, $options, $confirmMessage);

    if (is_null($wrapper))
	  return $link;

	if (1 == substr_count($wrapper, '%s'))
      return sprintf($wrapper, $link);
	return sprintf($wrapper, '', $link);
  }

  /**
   *
   * return true if the user if logged in or false otherwise
   */
  public function isLoggedin()
  {
    return !is_null($this->__foreignKey);
  }

  /**
   *
   * You must use  Acl->endBlock() before the end of the view
   * Begin a block which will be displayed only
   * if there is an Acl->link() successful
   * before the endBlock
   */
  public function startBlock()
  {
    $this->__blocks[] = false;
    ob_start();
  }

  /**
   *
   * End the current block.
   * This block is displayed if it contains
   * at least one successfully displayed link
   */
  public function endBlock()
  {
    $lastid = count($this->__blocks) - 1;
    if ($this->__blocks[$lastid])
      ob_end_flush();
    else
      ob_end_clean();

    unset($this->__blocks[$lastid]);
  }
}
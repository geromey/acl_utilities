<?php
class AclHelper extends AppHelper
{
  public $name = 'Acl';
  public $helpers = array('Session', 'Html');

  /**
   *
   * foreign key of the aro
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
   * @var AclComponent²
   */
  private $__acl;

  public function beforeRender()
  {
    parent::beforeRender();

    $this->__allowedActions = Configure::read('AclUtilities.allowedActions');

    $this->__foreignKey = $this->Session->read('Auth.User.id');

    // if not logged in, then no need for the Acl
    if (is_null($this->__foreignKey))
      return;

    App::import('Component', 'Acl');
    $this->__acl = new AclComponent();
  }


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
    if (is_null($this->__foreignKey))
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

  public function link($title, $url = null, $options = array(), $confirmMessage = false)
  {
    if (!$this->check($url))
      return '';

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

  public function isLoggedin()
  {
    return !is_null($this->__foreignKey);
  }
}
<?php
App::uses('AppModel', 'Model');
App::uses('AuthComponent', 'Controller/Component');

/**
 * User Model
 *
 * @property Group $Group
 */
class User extends AppModel {

    //Used to build the list of children an Access Provider may have up to the end nodes.
    private $ap_children    = array();

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'username' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'password' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'group_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
	);

	//The Associations below have been created with all possible keys, those that are not needed can be removed

/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
        'Country' => array(
            'className'     => 'Country',
			'foreignKey'    => 'country_id'
        ),
		'Group' => array(
			'className' => 'Group',
			'foreignKey' => 'group_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		),
        'Language' => array(
            'className'     => 'Language',
			'foreignKey'    => 'language_id'
        ),
        'Realm' => array(
            'className'     => 'Realm',
			'foreignKey'    => 'realm_id'
        ),
        'Owner' => array(
            'className'     => 'User',
            'foreignKey'    => 'parent_id'
        )
        
	);

    public $hasMany = array(
        'Na',
        'Tag',
        'Realm',
        'UserNote' => array(
            'dependent'     => true   
        )
    );

    public $actsAs = array('Acl' => array('type' => 'requester'),'Containable','Tree');


    public function beforeSave($options = array()) {

        if((isset($this->data['User']['token']))&&($this->data['User']['token']=='')){
            App::uses('String', 'Utility');
            $this->data['User']['token'] = String::uuid();
        }

        if(isset($this->data['User']['password'])){
            $this->data['User']['password'] = AuthComponent::password($this->data['User']['password']);
        }
        return true;
    }


    //This function is required for the Acl behaviour....
    public function parentNode() {
        if (!$this->id && empty($this->data)) {
            return null;
        }
        if (isset($this->data['User']['group_id'])) {
            $groupId = $this->data['User']['group_id'];
        } else {
            $groupId = $this->field('group_id');
        }
        if (!$groupId) {
            return null;
        } else {
            return array('Group' => array('id' => $groupId));
        }
    }

    //Used to get a list of all the access provider's owned by $ap_id, including their children and children's children etc...
    public function find_access_provider_children($ap_id){

        $ap_name = Configure::read('group.ap');

        $this->contain();
        $parent = $this->findById($ap_id);
        $this->contain('Group');
        $parentAndChildren = $this->find('threaded', array(
            'conditions' => array(
                'User.lft >='    => $parent['User']['lft'], 
                'User.rght <='   => $parent['User']['rght'],
                'Group.name'    => $ap_name
            )
        ));

        $this->_build_access_provider_children($parentAndChildren);
        return $this->ap_children;
    }

    //Called recusrively to find the children downward from an AP
    private function _build_access_provider_children($results){
        foreach($results as $i){
            $id         = $i['User']['id'];
            $username   = $i['User']['username'];
            array_push($this->ap_children,array('id' => $id, 'username' => $username));
            if(count($i['children']) > 0){ //Call recursivley
                $this->_build_access_provider_children($i['children']);
            }
        }
    }
}

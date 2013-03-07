<?php
App::uses('AppModel', 'Model');
/**
 * Realm Model
 *
 * @property User $User
 */
class Realm extends AppModel {

    public $actsAs = array('Acl' => array('className' => 'MyAcl','type' => 'controlled'),'Containable');


/**
 * Display field
 *
 * @var string
 */
	public $displayField = 'name';

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
        'name' => array(
            'required' => array(
                'rule' => array('notEmpty'),
                'message' => 'Value is required'
            ),
            'unique' => array(
                'rule'    => 'isUnique',
                'message' => 'This name is already taken'
            )
        )
    );
	//The Associations below have been created with all possible keys, those that are not needed can be removed

/**
 * hasMany associations
 *
 * @var array
 */
	public $hasMany = array(
        'NaRealm'   =>array(
            'dependent' => false   
        ),
        'RealmNote' => array(
            'dependent' => true
        )
	);

    public $belongsTo = array(
        'User' => array(
            'className'     => 'User',
			'foreignKey'    => 'user_id'
        )
	);


    function parentNode() {
       // return null;
        //See if there is a node with a name called "Realms" and who has no parent in the aco's table
        //App::uses('classRegistry','Utility');

        $alias = 'Realms';
        $aco = ClassRegistry::init('Aco');

        $q_r = $aco->find('first',array('conditions' => array('Aco.parent_id' => null, 'Aco.alias' => $alias)));

        if(!isset($q_r['Aco']['id'])){
            $aco->create();
            $aco->save(array('alias' => $alias));
        }
        return $alias;
    }
}

<?php
App::uses('AppController', 'Controller');

class NasController extends AppController {


    public $name       = 'Nas';
    public $components = array('Aa','RequestHandler');
    public $uses       = array('Na','User');
    protected $base    = "Access Providers/Controllers/Nas/";

    protected $tmpDir  = 'csvexport';

//------------------------------------------------------------------------

    public function export_csv(){

        $this->autoRender   = false;

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        //Build query
        $user_id    = $user['id'];
        $c          = $this->_build_common_query($user);
        $q_r        = $this->Na->find('all',$c);

        //Create file
        $this->ensureTmp();     
        $tmpFilename    = TMP . $this->tmpDir . DS .  strtolower( Inflector::pluralize($this->modelClass) ) . '-' . date('Ymd-Hms') . '.csv';
        $fp             = fopen($tmpFilename, 'w');

        //Headings
        $heading_line   = array();
        if(isset($this->request->query['columns'])){
            $columns = json_decode($this->request->query['columns']);
            foreach($columns as $c){
                array_push($heading_line,$c->name);
            }
        }
        fputcsv($fp, $heading_line,';','"');

        //Results
        foreach($q_r as $i){

            $columns    = array();
            $csv_line   = array();
            if(isset($this->request->query['columns'])){
                $columns = json_decode($this->request->query['columns']);
                foreach($columns as $c){
                    //Realms
                    $column_name = $c->name;
                    if($column_name == 'realms'){
                        $realms = '';
                        foreach($i['NaRealm'] as $nr){
                            if(!$this->_test_for_private_parent($nr['Realm'],$user)){
                                $realms = $realms.'['.$nr['Realm']['name'].']';
                            }
                        }
                        array_push($csv_line,$realms);
                    }elseif($column_name == 'tags'){
                        $tags   = '';
                        foreach($i['NaTag'] as $nr){
                            if(!$this->_test_for_private_parent($nr['Tag'],$user)){
                                $tags = $tags.'['.$nr['Tag']['name'].']';    
                            }
                        }
                        array_push($csv_line,$tags);
                    }elseif($column_name == 'notes'){
                        $notes   = '';
                        foreach($i['NaNote'] as $n){
                            if(!$this->_test_for_private_parent($n['Note'],$user)){
                                $notes = $notes.'['.$n['Note']['note'].']';    
                            }
                        }
                        array_push($csv_line,$notes);
                    }elseif($column_name =='owner'){
                        $owner_id       = $i['Na']['user_id'];
                        $owner_tree     = $this->_find_parents($owner_id);
                        array_push($csv_line,$owner_tree); 
                    }else{
                        array_push($csv_line,$i['Na']["$column_name"]);  
                    }
                }
                fputcsv($fp, $csv_line,';','"');
            }
        }

        //Return results
        fclose($fp);
        $data = file_get_contents( $tmpFilename );
        $this->cleanupTmp( $tmpFilename );
        $this->RequestHandler->respondAs('csv');
        $this->response->download( strtolower( Inflector::pluralize( $this->modelClass ) ) . '.csv' );
        $this->response->body($data);
    }

    //____ BASIC CRUD  Manager ________
    public function index(){
        //Display a list of items with their owners
        //This will be dispalyed to the Administrator as well as Access Providers who has righs

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        $c = $this->_build_common_query($user); 

        //===== PAGING (MUST BE LAST) ======
        $limit  = 50;   //Defaults
        $page   = 1;
        $offset = 0;
        if(isset($this->request->query['limit'])){
            $limit  = $this->request->query['limit'];
            $page   = $this->request->query['page'];
            $offset = $this->request->query['start'];
        }

      //  print_r($c);

        $c_page             = $c;
        $c_page['page']     = $page;
        $c_page['limit']    = $limit;
        $c_page['offset']   = $offset;

        $total  = $this->Na->find('count',$c);       
        $q_r    = $this->Na->find('all',$c_page);

        $items = array();
        foreach($q_r as $i){

            $realms     = array();
            //Realms
            foreach($i['NaRealm'] as $nr){
                if(!$this->_test_for_private_parent($nr['Realm'],$user)){
                    array_push($realms, 
                        array(
                            'id'                    => $nr['Realm']['id'],
                            'name'                  => $nr['Realm']['name'],
                            'available_to_siblings' => $nr['Realm']['available_to_siblings']
                        ));
                }
            } 

            //Create tags list
            $tags       = array();
            foreach($i['NaTag'] as $nr){
                if(!$this->_test_for_private_parent($nr['Tag'],$user)){
                    array_push($tags, 
                        array(
                            'id'                    => $nr['Tag']['id'],
                            'name'                  => $nr['Tag']['name'],
                            'available_to_siblings' => $nr['Tag']['available_to_siblings']
                    ));
                }
            }

            //Create notes flag
            $notes_flag  = false;
            foreach($i['NaNote'] as $nn){
                if(!$this->_test_for_private_parent($nn['Note'],$user)){
                    $notes_flag = true;
                    break;
                }
            }

            $owner_id       = $i['Na']['user_id'];
            $owner_tree     = $this->_find_parents($owner_id);
            $action_flags   = $this->_get_action_flags($owner_id,$user);
      
            array_push($items,array(
                'id'                    => $i['Na']['id'], 
                'nasname'               => $i['Na']['nasname'],
                'shortname'             => $i['Na']['shortname'],
                'secret'                => $i['Na']['secret'],
                'type'                  => $i['Na']['type'],
                'ports'                 => $i['Na']['ports'],
                'community'             => $i['Na']['community'],
                'server'                => $i['Na']['server'],
                'description'           => $i['Na']['description'],
                'connection_type'       => $i['Na']['connection_type'],
                'record_auth'           => $i['Na']['record_auth'],
                'dynamic_attribute'     => $i['Na']['dynamic_attribute'],
                'dynamic_value'         => $i['Na']['dynamic_value'],
                'monitor'               => $i['Na']['monitor'],
                'ping_interval'         => $i['Na']['ping_interval'],
                'heartbeat_dead_after'  => $i['Na']['heartbeat_dead_after'],
                'session_auto_close'    => $i['Na']['session_auto_close'],
                'session_dead_time'     => $i['Na']['session_dead_time'],
                'on_public_maps'        => $i['Na']['on_public_maps'],
                'lat'                   => $i['Na']['lat'],
                'lon'                   => $i['Na']['lon'],
                'owner'                 => $owner_tree, 
                'user_id'               => $i['Na']['user_id'],
                'available_to_siblings' => $i['Na']['available_to_siblings'],
                'notes'                 => $notes_flag,
                'realms'                => $realms,
                'tags'                  => $tags,
                'connection_type'       => $i['Na']['connection_type'],
                'update'                => $action_flags['update'],
                'delete'                => $action_flags['delete'],
                'manage_tags'           => $action_flags['manage_tags']
            ));
        }

        //___ FINAL PART ___
        $this->set(array(
            'items' => $items,
            'success' => true,
            'totalCount' => $total,
            '_serialize' => array('items','success','totalCount')
        ));
    }

    public function add() {

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        //Get the creator's id
         if($this->request->data['user_id'] == '0'){ //This is the holder of the token - override '0'
            $this->request->data['user_id'] = $user_id;
        }

        //Make available to siblings check
        if(isset($this->request->data['available_to_siblings'])){
            $this->request->data['available_to_siblings'] = 1;
        }else{
            $this->request->data['available_to_siblings'] = 0;
        }

        //If this attribute is not present it will fail empty check
        if(!isset($this->request->data['nasname'])){
            $this->request->data['nasname'] = ''; //Make it empty if not present
        }

        //We need to see what the connection type was that was chosen
        $connection_type = 'direct'; //Default
        if(isset($this->request->data['connection_type'])){
            if($this->request->data['connection_type'] == 'openvpn'){   //Add the OpenVPN item
                $d = array();
                $d['OpenvpnClient']['username'] = $this->request->data['vpn_username'];
                $d['OpenvpnClient']['password'] = $this->request->data['vpn_password'];
                $this->Na->OpenvpnClient->create();
                if(!$this->Na->OpenvpnClient->save($d)){
                    $first_error = reset($this->Na->OpenvpnClient->validationErrors);
                    $this->set(array(
                        'errors'    => $this->Na->OpenvpnClient->validationErrors,
                        'success'   => false,
                        'message'   => array('message' => 'Could not create OpenVPN Client <br>'.$first_error[0]),
                        '_serialize' => array('errors','success','message')
                    ));
                    return;
                }else{
                    //Derive the nasname (ip address) from the new OpenvpnClient entry
                    $qr = $this->Na->OpenvpnClient->findById($this->Na->OpenvpnClient->id);
                    //IP Address =
                    $nasname = Configure::read('openvpn.ip_half').$qr['OpenvpnClient']['subnet'].'.'.$qr['OpenvpnClient']['peer1'];
                    $this->request->data['nasname'] = $nasname;
                }
            }
        }

        if(isset($this->request->data['connection_type'])){
            if($this->request->data['connection_type'] == 'dynamic'){   //Add discover the next dynamic-<number>

                $qr = $this->Na->find('first',array('conditions' => array('Na.nasname LIKE' => 'dynamic-%'), 'order' => 'Na.nasname DESC'));
                if($qr == ''){
                    $this->request->data['nasname'] = 'dynamic-1';
                }else{
                    $last_id = $qr['Na']['nasname'];
                    $last_nr = preg_replace('/^dynamic-/', "", $last_id);
                    //See if there are not any holes:
                    $start_id = 1;
                    $hole_flag = false;
                    while($start_id < $last_nr){
                        $nn = 'dynamic-'.$start_id;
                        $count = $this->Na->find('count', array('conditions' => array('Na.nasname' => $nn))); //This name is missing; we can take it
                        if($count == 0){
                            $this->request->data['nasname'] = $nn; 
                            $hole_flag = true;
                            break;
                        }
                        $start_id++;
                    }
                    if(!$hole_flag){ //There was no gap; take the next number
                        $this->request->data['nasname'] = 'dynamic-'.($last_nr+1);
                    }     
                }   
            }
        }

        $this->{$this->modelClass}->create();
        //print_r($this->request->data);
        if ($this->{$this->modelClass}->save($this->request->data)) {

            

            //Check if we need to add na_realms table
            if(isset($this->request->data['avail_for_all'])){
            //Available to all does not add any na_realm entries
            }else{
                foreach(array_keys($this->request->data) as $key){
                    if(preg_match('/^\d+/',$key)){
                        //----------------
                        $this->_add_nas_realm($this->{$this->modelClass}->id,$key);
                        //-------------
                    }
                }
            }

            //If it was an OpenvpnClient we need to update the na_id field
            if($this->request->data['connection_type'] == 'openvpn'){
                $this->Na->OpenvpnClient->saveField('na_id', $this->{$this->modelClass}->id);
            }
          
            $this->set(array(
                'success' => true,
                '_serialize' => array('success')
            ));
        } else {
            //If it was an OpenvpnClient we need to remove the created openvpnclient entry since there was a failure
            if($this->request->data['connection_type'] == 'openvpn'){
                $this->Na->OpenvpnClient->delete();
            }

            $first_error = reset($this->{$this->modelClass}->validationErrors);
            $this->set(array(
                'errors'    => $this->{$this->modelClass}->validationErrors,
                'success'   => false,
                'message'   => array('message' => __('Could not create item').' <br>'.$first_error[0]),
                '_serialize' => array('errors','success','message')
            ));
        }

	}

    public function add_direct(){

         //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        $conn_type = 'direct';
        $this->request->data['connection_type'] = $conn_type;

        //Get the creator's id
         if($this->request->data['user_id'] == '0'){ //This is the holder of the token - override '0'
            $this->request->data['user_id'] = $user_id;
        }

        //Make available to siblings check
        if(isset($this->request->data['available_to_siblings'])){
            $this->request->data['available_to_siblings'] = 1;
        }else{
            $this->request->data['available_to_siblings'] = 0;
        }

        //Then we add the rest.....
        $this->{$this->modelClass}->create();
        //print_r($this->request->data);
        if ($this->{$this->modelClass}->save($this->request->data)) {

            //Check if we need to add na_realms table
            if(isset($this->request->data['avail_for_all'])){
            //Available to all does not add any na_realm entries
            }else{
                foreach(array_keys($this->request->data) as $key){
                    if(preg_match('/^\d+/',$key)){
                        //----------------
                        $this->_add_nas_realm($this->{$this->modelClass}->id,$key);
                        //-------------
                    }
                }
            }   
            $this->set(array(
                'success' => true,
                '_serialize' => array('success')
            ));
        } else {
            $first_error = reset($this->{$this->modelClass}->validationErrors);
            $this->set(array(
                'errors'    => $this->{$this->modelClass}->validationErrors,
                'success'   => false,
                'message'   => array('message' => __('Could not create item').' <br>'.$first_error[0]),
                '_serialize' => array('errors','success','message')
            ));
        }
    }

    public function add_open_vpn(){

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        $conn_type = 'openvpn';
        $this->request->data['connection_type'] = $conn_type;

        //Get the creator's id
         if($this->request->data['user_id'] == '0'){ //This is the holder of the token - override '0'
            $this->request->data['user_id'] = $user_id;
        }

        //Make available to siblings check
        if(isset($this->request->data['available_to_siblings'])){
            $this->request->data['available_to_siblings'] = 1;
        }else{
            $this->request->data['available_to_siblings'] = 0;
        }

        //If this attribute is not present it will fail empty check
        if(!isset($this->request->data['nasname'])){
            $this->request->data['nasname'] = ''; //Make it empty if not present
        }

        //First we create the OpenVPN entry....
        $d = array();
        $d['OpenvpnClient']['username'] = $this->request->data['username'];
        $d['OpenvpnClient']['password'] = $this->request->data['password'];
        $this->Na->OpenvpnClient->create();
        if(!$this->Na->OpenvpnClient->save($d)){
            $first_error = reset($this->Na->OpenvpnClient->validationErrors);
            $this->set(array(
                'errors'    => $this->Na->OpenvpnClient->validationErrors,
                'success'   => false,
                'message'   => array('message' => 'Could not create OpenVPN Client <br>'.$first_error[0]),
                '_serialize' => array('errors','success','message')
            ));
            return;
        }else{
            //Derive the nasname (ip address) from the new OpenvpnClient entry
            $qr = $this->Na->OpenvpnClient->findById($this->Na->OpenvpnClient->id);
            //IP Address =
            $nasname = Configure::read('openvpn.ip_half').$qr['OpenvpnClient']['subnet'].'.'.$qr['OpenvpnClient']['peer1'];
            $this->request->data['nasname'] = $nasname;
        }

        //Then we add the rest.....
        $this->{$this->modelClass}->create();
        //print_r($this->request->data);
        if ($this->{$this->modelClass}->save($this->request->data)) {

            //Check if we need to add na_realms table
            if(isset($this->request->data['avail_for_all'])){
            //Available to all does not add any na_realm entries
            }else{
                foreach(array_keys($this->request->data) as $key){
                    if(preg_match('/^\d+/',$key)){
                        //----------------
                        $this->_add_nas_realm($this->{$this->modelClass}->id,$key);
                        //-------------
                    }
                }
            }

            //Save the new ID to the OpenvpnClient....
            $this->Na->OpenvpnClient->saveField('na_id', $this->{$this->modelClass}->id);
          
            $this->set(array(
                'success' => true,
                '_serialize' => array('success')
            ));
        } else {
            //If it was an OpenvpnClient we need to remove the created openvpnclient entry since there was a failure
            $this->Na->OpenvpnClient->delete();
            $first_error = reset($this->{$this->modelClass}->validationErrors);
            $this->set(array(
                'errors'    => $this->{$this->modelClass}->validationErrors,
                'success'   => false,
                'message'   => array('message' => __('Could not create item').' <br>'.$first_error[0]),
                '_serialize' => array('errors','success','message')
            ));
        }
    }

    public function add_dynamic(){
        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        $conn_type = 'dynamic';
        $this->request->data['connection_type'] = $conn_type;

        //Get the creator's id
         if($this->request->data['user_id'] == '0'){ //This is the holder of the token - override '0'
            $this->request->data['user_id'] = $user_id;
        }

        //Make available to siblings check
        if(isset($this->request->data['available_to_siblings'])){
            $this->request->data['available_to_siblings'] = 1;
        }else{
            $this->request->data['available_to_siblings'] = 0;
        }

        //If this attribute is not present it will fail empty check
        if(!isset($this->request->data['nasname'])){
            $this->request->data['nasname'] = ''; //Make it empty if not present
        }

        $qr = $this->Na->find('first',array('conditions' => array('Na.nasname LIKE' => 'dynamic-%'), 'order' => 'Na.nasname DESC'));
        if($qr == ''){
            $this->request->data['nasname'] = 'dynamic-1';
        }else{
            $last_id = $qr['Na']['nasname'];
            $last_nr = preg_replace('/^dynamic-/', "", $last_id);
            //See if there are not any holes:
            $start_id = 1;
            $hole_flag = false;
            while($start_id < $last_nr){
                $nn = 'dynamic-'.$start_id;
                $count = $this->Na->find('count', array('conditions' => array('Na.nasname' => $nn))); //This name is missing; we can take it
                if($count == 0){
                    $this->request->data['nasname'] = $nn; 
                    $hole_flag = true;
                    break;
                }
                $start_id++;
            }
            if(!$hole_flag){ //There was no gap; take the next number
                $this->request->data['nasname'] = 'dynamic-'.($last_nr+1);
            }     
        }

        //Then we add the rest.....
        $this->{$this->modelClass}->create();
        //print_r($this->request->data);
        if ($this->{$this->modelClass}->save($this->request->data)) {

            //Check if we need to add na_realms table
            if(isset($this->request->data['avail_for_all'])){
            //Available to all does not add any na_realm entries
            }else{
                foreach(array_keys($this->request->data) as $key){
                    if(preg_match('/^\d+/',$key)){
                        //----------------
                        $this->_add_nas_realm($this->{$this->modelClass}->id,$key);
                        //-------------
                    }
                }
            }   
            $this->set(array(
                'success' => true,
                '_serialize' => array('success')
            ));
        } else {
            $first_error = reset($this->{$this->modelClass}->validationErrors);
            $this->set(array(
                'errors'    => $this->{$this->modelClass}->validationErrors,
                'success'   => false,
                'message'   => array('message' => __('Could not create item').' <br>'.$first_error[0]),
                '_serialize' => array('errors','success','message')
            ));
        }
    }

    public function add_pptp(){
          //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        $conn_type = 'pptp';
        $this->request->data['connection_type'] = $conn_type;

        //Get the creator's id
         if($this->request->data['user_id'] == '0'){ //This is the holder of the token - override '0'
            $this->request->data['user_id'] = $user_id;
        }

        //Make available to siblings check
        if(isset($this->request->data['available_to_siblings'])){
            $this->request->data['available_to_siblings'] = 1;
        }else{
            $this->request->data['available_to_siblings'] = 0;
        }

        //If this attribute is not present it will fail empty check
        if(!isset($this->request->data['nasname'])){
            $this->request->data['nasname'] = ''; //Make it empty if not present
        }

        //First we create the OpenVPN entry....
        $d = array();
        $d['PptpClient']['username'] = $this->request->data['username'];
        $d['PptpClient']['password'] = $this->request->data['password'];
        $this->{$this->modelClass}->PptpClient->create();
        if(!$this->{$this->modelClass}->PptpClient->save($d)){
            $first_error = reset($this->{$this->modelClass}->PptpClient->validationErrors);
            $this->set(array(
                'errors'    => $this->{$this->modelClass}->PptpClient->validationErrors,
                'success'   => false,
                'message'   => array('message' => 'Could not create OpenVPN Client <br>'.$first_error[0]),
                '_serialize' => array('errors','success','message')
            ));
            return;
        }else{
            //Derive the nasname (ip address) from the new PptpClient entry
            $qr = $this->{$this->modelClass}->PptpClient->findById($this->Na->PptpClient->id);
            //IP Address =
            $nasname = $qr['PptpClient']['ip'];
            $this->request->data['nasname'] = $nasname;
        }

        //Then we add the rest.....
        $this->{$this->modelClass}->create();
        //print_r($this->request->data);
        if ($this->{$this->modelClass}->save($this->request->data)) {

            //Check if we need to add na_realms table
            if(isset($this->request->data['avail_for_all'])){
            //Available to all does not add any na_realm entries
            }else{
                foreach(array_keys($this->request->data) as $key){
                    if(preg_match('/^\d+/',$key)){
                        //----------------
                        $this->_add_nas_realm($this->{$this->modelClass}->id,$key);
                        //-------------
                    }
                }
            }

            //Save the new ID to the PptpClient....
            $this->{$this->modelClass}->PptpClient->saveField('na_id', $this->{$this->modelClass}->id);
          
            $this->set(array(
                'success' => true,
                '_serialize' => array('success')
            ));
        } else {
            //If it was an PptpClient we need to remove the created pptpclient entry since there was a failure
            $this->{$this->modelClass}->PptpClient->delete();
            $first_error = reset($this->{$this->modelClass}->validationErrors);
            $this->set(array(
                'errors'    => $this->{$this->modelClass}->validationErrors,
                'success'   => false,
                'message'   => array('message' => __('Could not create item').' <br>'.$first_error[0]),
                '_serialize' => array('errors','success','message')
            ));
        }
    }

    public function view_openvpn(){
        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        $items = array();
        if(isset($this->request->query['nas_id'])){

            $q_r = $this->{$this->modelClass}->OpenvpnClient->find('first',
                array('conditions' => array('OpenvpnClient.na_id' => $this->request->query['nas_id']))
            );

            if($q_r){
                $items['username'] = $q_r['OpenvpnClient']['username'];
                $items['password'] = $q_r['OpenvpnClient']['password'];
            }
        }

        $this->set(array(
            'data'   => $items, //For the form to load we use data instead of the standard items as for grids
            'success' => true,
            '_serialize' => array('success','data')
        ));
    }

    public function edit_openvpn(){

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];
        //TODO Check if the owner ...

        if(isset($this->request->data['nas_id'])){

            $q_r = $this->{$this->modelClass}->OpenvpnClient->find('first',
                array('conditions' => array('OpenvpnClient.na_id' => $this->request->data['nas_id']))
            );

            if($q_r){
                $id = $q_r['OpenvpnClient']['id'];
                $this->request->data['id']      = $id;
                $this->request->data['subnet']  = $q_r['OpenvpnClient']['subnet'];
                $this->request->data['peer1']   = $q_r['OpenvpnClient']['peer1'];
                $this->request->data['peer2']   = $q_r['OpenvpnClient']['peer2'];  
            }

            if(!$this->Na->OpenvpnClient->save($this->request->data)){
                $first_error = reset($this->Na->OpenvpnClient->validationErrors);
                $this->set(array(
                    'errors'    => $this->Na->OpenvpnClient->validationErrors,
                    'success'   => false,
                    'message'   => array('message' => __('Could not update OpenVPN detail').' <br>'.$first_error[0]),
                    '_serialize' => array('errors','success','message')
                ));
                return;
            }else{
                    $this->set(array(
                    'success' => true,
                    '_serialize' => array('success')
                ));
            }
        }
    }

    public function view_pptp(){
        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        $items = array();
        if(isset($this->request->query['nas_id'])){

            $q_r = $this->{$this->modelClass}->PptpClient->find('first',
                array('conditions' => array('PptpClient.na_id' => $this->request->query['nas_id']))
            );

            if($q_r){
                $items['username'] = $q_r['PptpClient']['username'];
                $items['password'] = $q_r['PptpClient']['password'];
            }
        }

        $this->set(array(
            'data'   => $items, //For the form to load we use data instead of the standard items as for grids
            'success' => true,
            '_serialize' => array('success','data')
        ));
    }

    public function edit_pptp(){

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];
        //TODO Check if the owner ...

        if(isset($this->request->data['nas_id'])){

            $q_r = $this->{$this->modelClass}->PptpClient->find('first',
                array('conditions' => array('PptpClient.na_id' => $this->request->data['nas_id']))
            );

            if($q_r){
                $id = $q_r['PptpClient']['id'];
                $this->request->data['id']      = $id;
                $this->request->data['ip']      = $q_r['PptpClient']['ip'];
            }

            if(!$this->Na->PptpClient->save($this->request->data)){
                $first_error = reset($this->Na->PptpClient->validationErrors);
                $this->set(array(
                    'errors'    => $this->Na->PptpClient->validationErrors,
                    'success'   => false,
                    'message'   => array('message' => __('Could not update PPTP detail').' <br>'.$first_error[0]),
                    '_serialize' => array('errors','success','message')
                ));
                return;
            }else{
                    $this->set(array(
                    'success' => true,
                    '_serialize' => array('success')
                ));
            }
        }
    }


    public function view_dynamic(){
        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        $items = array();
        if(isset($this->request->query['nas_id'])){

            $q_r = $this->{$this->modelClass}->findById($this->request->query['nas_id']);

            if($q_r){
                $items['dynamic_attribute'] = $q_r['Na']['dynamic_attribute'];
                $items['dynamic_value']     = $q_r['Na']['dynamic_value'];
            }
        }

        $this->set(array(
            'data'   => $items, //For the form to load we use data instead of the standard items as for grids
            'success' => true,
            '_serialize' => array('success','data')
        ));
    }

    public function edit_dynamic(){
        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        if ($this->{$this->modelClass}->save($this->request->data)) {
            $this->set(array(
                'success' => true,
                '_serialize' => array('success')
            ));

        }else{
             $this->set(array(
                'success' => false,
                '_serialize' => array('success')
            ));
        }
    }

    public function view_nas(){
        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        $items = array();
        if(isset($this->request->query['nas_id'])){

            $q_r = $this->{$this->modelClass}->findById($this->request->query['nas_id']);

            if($q_r){
                $items = $q_r['Na'];
            }
        }

        $this->set(array(
            'data'   => $items, //For the form to load we use data instead of the standard items as for grids
            'success' => true,
            '_serialize' => array('success','data')
        ));
    }

    public function edit_nas(){
        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        if($this->request->data['monitor'] == 'off'){   //Clear the last contact when off
            $this->request->data['last_contact'] = null;
        }

        if ($this->{$this->modelClass}->save($this->request->data)) {
            $this->set(array(
                'success' => true,
                '_serialize' => array('success')
            ));

        }else{
             $this->set(array(
                'success' => false,
                '_serialize' => array('success')
            ));
        }
    }

    public function manage_tags(){

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];


        $tag_id = $this->request->data['tag_id'];
        $rb     = $this->request->data['rb'];

        foreach(array_keys($this->request->data) as $key){
            if(preg_match('/^\d+/',$key)){
                //----------------
                if($rb == 'add'){
                    $this->_add_nas_tag($key,$tag_id);
                }
                if($rb == 'remove'){
                    $this->Na->NaTag->deleteAll(array('NaTag.na_id' => $key,'NaTag.tag_id' => $tag_id), false);
                }
                //-------------
            }
        }
     
        $this->set(array(
                'success' => true,
                '_serialize' => array('success')
        ));
    }

    public function edit_panel_cfg(){

        $items = array();
        $nn_disabled = true;

        //Determine which tabs will be displayed (based on the connection type)
        if(isset($this->request->query['nas_id'])){
            $q_r = $this->{$this->modelClass}->findById($this->request->query['nas_id']);
            if($q_r){
                $conn_type = $q_r['Na']['connection_type'];
                if($conn_type == 'openvpn'){
                    array_push($items,array( 'title'  => __('OpenVPN'), 'itemId' => 'tabOpenVpn', 'xtype' => 'pnlNasOpenVpn'));
                }
                if($conn_type == 'pptp'){
                    array_push($items,array( 'title'  => __('PPTP'),    'itemId' => 'tabPptp', 'xtype' => 'pnlNasPptp'));
                }
                if($conn_type == 'dynamic'){
                    array_push($items,array( 'title'  => __('Dynamic AVP detail'), 'itemId' => 'tabDynamic', 'xtype' => 'pnlNasDynamic' ));
                }
                if($conn_type == 'direct'){
                    $nn_disabled = false;
                }
            }
        }

        //This will be with all of them
       /// array_push($items, array( 'title'  => __('NAS'), 'itemId' => 'tabNas', 'layout' => 'hbox', 
       ///     'items' => array('xtype' => 'frmNasBasic', 'height' => '100%', 'width' => 500)
       /// ));
         array_push($items, array( 'title'  => __('NAS'), 'itemId' => 'tabNas', 'xtype' => 'pnlNasNas', 'nn_disabled' => $nn_disabled));
        array_push($items,array( 'title'  => __('Realms'),'itemId' => 'tabRealms', 'layout' => 'fit', 'border' => false, 'xtype' => 'pnlRealmsForNasOwner'));
      //  array_push($items,array( 'title'  => __('Photo')));
      //  array_push($items,array( 'title'  => __('Availability')));
       // array_push($items,array( 'title'  => __('Map info')));

        $this->set(array(
                'items'     => $items,
                'success'   => true,
                '_serialize' => array('items','success')
        ));

    }

    public function edit() {

       if ($this->{$this->modelClass}->save($this->request->data)) {
            $this->set(array(
                'success' => true,
                '_serialize' => array('success')
            ));
        }else{
             $this->set(array(
                'errors'    => $this->{$this->modelClass}->validationErrors,
                'success'   => false,
                'message'   => array('message' => __('Could not update item')),
                '_serialize' => array('errors','success','message')
            ));
        }
	}


    public function delete($id = null) {
    //FIXME This is seriously wrong! it is going to delete wrong stuff!
		if (!$this->request->is('post')) {
			throw new MethodNotAllowedException();
		}

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];  

	    if(isset($this->data['id'])){   //Single item delete
            $message = __("Single item")." ".$this->data['id'];
            $this->{$this->modelClass}->id = $this->data['id'];
            $this->{$this->modelClass}->delete();
      
        }else{                          //Assume multiple item delete
            foreach($this->data as $d){
                $this->{$this->modelClass}->id = $d['id'];
                $this->{$this->modelClass}->delete();
            }
        }

        $this->set(array(
            'success' => true,
            '_serialize' => array('success')
        ));
	}


    public function note_index(){

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        $items = array();
        if(isset($this->request->query['for_id'])){
            $na_id  = $this->request->query['for_id'];
            $q_r    = $this->Na->NaNote->find('all', 
                array(
                    'contain'       => array('Note'),
                    'conditions'    => array('NaNote.na_id' => $na_id)
                )
            );
            foreach($q_r as $i){
                if(!$this->_test_for_private_parent($i['Note'],$user)){
                    $owner_id   = $i['Note']['user_id'];
                    $owner      = $this->_find_parents($owner_id);
                    $afs        = $this->_get_action_flags($owner_id,$user);
                    array_push($items,
                        array(
                            'id'        => $i['Note']['id'], 
                            'note'      => $i['Note']['note'], 
                            'available_to_siblings' => $i['Note']['available_to_siblings'],
                            'owner'     => $owner,
                            'delete'    => $afs['delete']
                        )
                    );
                }
            }
        } 
        $this->set(array(
            'items'     => $items,
            'success'   => true,
            '_serialize'=> array('success', 'items')
        ));
    }

    public function note_add(){

        //__ Authentication + Authorization __
        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }
        $user_id    = $user['id'];

        //Get the creator's id
        if($this->request->data['user_id'] == '0'){ //This is the holder of the token - override '0'
            $this->request->data['user_id'] = $user_id;
        }

        //Make available to siblings check
        if(isset($this->request->data['available_to_siblings'])){
            $this->request->data['available_to_siblings'] = 1;
        }else{
            $this->request->data['available_to_siblings'] = 0;
        }

        $success    = false;
        $msg        = array('message' => __('Could not create note'));
        $this->Na->NaNote->Note->create(); 
        //print_r($this->request->data);
        if ($this->Na->NaNote->Note->save($this->request->data)) {
            $d                      = array();
            $d['NaNote']['na_id']   = $this->request->data['for_id'];
            $d['NaNote']['note_id'] = $this->Na->NaNote->Note->id;
            $this->Na->NaNote->create();
            if ($this->Na->NaNote->save($d)) {
                $success = true;
            }
        }

        if($success){
            $this->set(array(
                'success' => $success,
                '_serialize' => array('success')
            ));
        }else{
             $this->set(array(
                'success' => $success,
                'message' => $message,
                '_serialize' => array('success','message')
            ));
        }
    }

    public function note_del(){

        if (!$this->request->is('post')) {
			throw new MethodNotAllowedException();
		}

        $user = $this->_ap_right_check();
        if(!$user){
            return;
        }

        $user_id    = $user['id'];
        $fail_flag  = false;

	    if(isset($this->data['id'])){   //Single item delete
            $message = "Single item ".$this->data['id'];

            //NOTE: we first check of the user_id is the logged in user OR a sibling of them:   
            $item       = $this->Na->NaNote->Note->findById($this->data['id']);
            $owner_id   = $item['Note']['user_id'];
            if($owner_id != $user_id){
                if($this->_is_sibling_of($user_id,$owner_id)== true){
                    $this->Na->NaNote->Note->id = $this->data['id'];
                    $this->Na->NaNote->Note->delete($this->data['id'],true);
                }else{
                    $fail_flag = true;
                }
            }else{
                $this->Na->NaNote->Note->id = $this->data['id'];
                $this->Na->NaNote->Note->delete($this->data['id'],true);
            }
   
        }else{                          //Assume multiple item delete
            foreach($this->data as $d){

                $item       = $this->Na->NaNote->Note->findById($d['id']);
                $owner_id   = $item['Note']['user_id'];
                if($owner_id != $user_id){
                    if($this->_is_sibling_of($user_id,$owner_id) == true){
                        $this->Na->NaNote->Note->id = $d['id'];
                        $this->Na->NaNote->Note->delete($d['id'],true);
                    }else{
                        $fail_flag = true;
                    }
                }else{
                    $this->Na->NaNote->Note->id = $d['id'];
                    $this->Na->NaNote->Note->delete($d['id'],true);
                }
   
            }
        }

        if($fail_flag == true){
            $this->set(array(
                'success'   => false,
                'message'   => array('message' => __('Could not delete some items')),
                '_serialize' => array('success','message')
            ));
        }else{
            $this->set(array(
                'success' => true,
                '_serialize' => array('success')
            ));
        }

    }


    //______ EXT JS UI functions ___________

    //------ List of available connection types ------
    //This is displayed as options when a user adds a new NAS device
    public function conn_types_available(){

        $items = array();

        $ct = Configure::read('conn_type');
        foreach($ct as $i){
            if($i['active']){
                array_push($items, $i);
            }
        }

        $this->set(array(
            'items' => $items,
            'success' => true,
            '_serialize' => array('items','success')
        ));
    }

    //------ List of configured dynamic attributes types ------
    //This is displayed as a select to choose from when the user adds a NAS of connection type dynamic
    public function dynamic_attributes(){
        $items = array();
        $ct = Configure::read('dynamic_attributes');
        foreach($ct as $i){
            if($i['active']){
                array_push($items, $i);
            }
        }

        $this->set(array(
            'items' => $items,
            'success' => true,
            '_serialize' => array('items','success')
        ));
    }

    //------ List of configured nas types  ------
    //This is displayed as a select to choose from when the user specifies the NAS detail 
    public function nas_types(){
        $items = array();
        $ct = Configure::read('nas_types');
        foreach($ct as $i){
            if($i['active']){
                array_push($items, $i);
            }
        }

        $this->set(array(
            'items' => $items,
            'success' => true,
            '_serialize' => array('items','success')
        ));
    }

    //----- Menus ------------------------
    public function menu_for_grid(){

        $user = $this->Aa->user_for_token($this);
        if(!$user){   //If not a valid user
            return;
        }

        //Empty by default
        $menu = array();

        //Admin => all power
        if($user['group_name'] == Configure::read('group.admin')){  //Admin

            $menu = array(
                    array('xtype' => 'buttongroup','title' => __('Action'), 'items' => array(
                        array( 'xtype' =>  'splitbutton',  'iconCls' => 'b-reload',   'scale'   => 'large', 'itemId'    => 'reload',   'tooltip'    => _('Reload'),
                            'menu'  => array( 
                                'items' => array( 
                                    '<b class="menu-title">Reload every:</b>',
                                  //  array( 'text'   => _('Cancel auto reload'),   'itemId' => 'mnuRefreshCancel', 'group' => 'refresh', 'checked' => true ),
                                    array( 'text'  => _('30 seconds'),      'itemId'    => 'mnuRefresh30s', 'group' => 'refresh','checked' => false ),
                                    array( 'text'  => _('1 minute'),        'itemId'    => 'mnuRefresh1m', 'group' => 'refresh' ,'checked' => false),
                                    array( 'text'  => _('5 minutes'),       'itemId'    => 'mnuRefresh5m', 'group' => 'refresh', 'checked' => false ),
                                    array( 'text'  => _('Stop auto reload'),'itemId'    => 'mnuRefreshCancel', 'group' => 'refresh', 'checked' => true )
                                   
                                )
                            )
                    ),
                   // array('xtype' => 'button', 'iconCls' => 'b-reload',  'scale' => 'large', 'itemId' => 'reload',   'tooltip'=> __('Reload')),
                    array('xtype' => 'button', 'iconCls' => 'b-add',     'scale' => 'large', 'itemId' => 'add',      'tooltip'=> __('Add')),
                    array('xtype' => 'button', 'iconCls' => 'b-delete',  'scale' => 'large', 'itemId' => 'delete',   'tooltip'=> __('Delete')),
                    array('xtype' => 'button', 'iconCls' => 'b-edit',    'scale' => 'large', 'itemId' => 'edit',     'tooltip'=> __('Edit'))
                )),
                array('xtype' => 'buttongroup','title' => __('Document'), 'items' => array(
                    array('xtype' => 'button', 'iconCls' => 'b-note',     'scale' => 'large', 'itemId' => 'note',     'tooltip'=> __('Add notes')),
                    array('xtype' => 'button', 'iconCls' => 'b-csv',     'scale' => 'large', 'itemId' => 'csv',      'tooltip'=> __('Export CSV')),
                )),
                array('xtype' => 'buttongroup','title' => __('Nas'), 'items' => array(
                    array('xtype' => 'button', 'iconCls' => 'b-meta_edit','scale' => 'large', 'itemId' => 'tag',     'tooltip'=> __('Manage tags')),
                    array('xtype' => 'button', 'iconCls' => 'b-map',     'scale' => 'large', 'itemId' => 'map',      'tooltip'=> __('Map'))
                )) 
            );
        }

        //AP depend on rights
        if($user['group_name'] == Configure::read('group.ap')){ //AP (with overrides)

            $id             = $user['id'];
            $action_group   = array();
            $document_group = array();
            $specific_group = array();
            array_push($action_group,array(  
                'xtype'     => 'button',
                'iconCls'   => 'b-reload',  
                'scale'     => 'large', 
                'itemId'    => 'reload',   
                'tooltip'   => __('Reload')));

            //Add
            if($this->Acl->check(array('model' => 'User', 'foreign_key' => $id), $this->base."add")){
                array_push($action_group,array(
                    'xtype'     => 'button', 
                    'iconCls'   => 'b-add',     
                    'scale'     => 'large', 
                    'itemId'    => 'add',      
                    'tooltip'   => __('Add')));
            }
            //Delete
            if($this->Acl->check(array('model' => 'User', 'foreign_key' => $id), $this->base.'delete')){
                array_push($action_group,array(
                    'xtype'     => 'button', 
                    'iconCls'   => 'b-delete',  
                    'scale'     => 'large', 
                    'itemId'    => 'delete',
                    'disabled'  => true,   
                    'tooltip'   => __('Delete')));
            }

            //Edit
            if($this->Acl->check(array('model' => 'User', 'foreign_key' => $id), $this->base.'edit')){
                array_push($action_group,array(
                    'xtype'     => 'button', 
                    'iconCls'   => 'b-edit',    
                    'scale'     => 'large', 
                    'itemId'    => 'edit',
                    'disabled'  => true,     
                    'tooltip'   => __('Edit')));
            }

            if($this->Acl->check(array('model' => 'User', 'foreign_key' => $id), $this->base.'note_index')){ 
                array_push($document_group,array(
                        'xtype'     => 'button', 
                        'iconCls'   => 'b-note',     
                        'scale'     => 'large', 
                        'itemId'    => 'note',      
                        'tooltip'   => __('Add Notes')));
            }

            if($this->Acl->check(array('model' => 'User', 'foreign_key' => $id), $this->base.'export_csv')){ 
                array_push($document_group,array(
                    'xtype'     => 'button', 
                    'iconCls'   => 'b-csv',     
                    'scale'     => 'large', 
                    'itemId'    => 'csv',      
                    'tooltip'   => __('Export CSV')));
            }

            //Tags
            if($this->Acl->check(array('model' => 'User', 'foreign_key' => $id), $this->base.'manage_tags')){
                array_push($specific_group,array(
                    'xtype'     => 'button', 
                    'iconCls'   => 'b-meta_edit',    
                    'scale'     => 'large', 
                    'itemId'    => 'tag',
                    'disabled'  => true,     
                    'tooltip'=> __('Manage tags')));
            }

            array_push($specific_group,array(
                    'xtype'     => 'button', 
                    'iconCls'   => 'b-map',     
                    'scale'     => 'large', 
                    'itemId'    => 'map',      
                    'tooltip'   => __('Maps')));

           // array_push($menu,array('xtype' => 'tbfill'));

            $menu = array(
                        array('xtype' => 'buttongroup','title' => __('Action'),        'items' => $action_group),
                        array('xtype' => 'buttongroup','title' => __('Document'),   'items' => $document_group),
                        array('xtype' => 'buttongroup','title' => __('Nas'),        'items' => $specific_group)
                    );
        }
        $this->set(array(
            'items'         => $menu,
            'success'       => true,
            '_serialize'    => array('items','success')
        ));
    }

    //______ END EXT JS UI functions ________

    private function _find_parents($id){

        $this->User->contain();//No dependencies
        $q_r        = $this->User->getPath($id);
        $path_string= '';
        if($q_r){

            foreach($q_r as $line_num => $i){
                $username       = $i['User']['username'];
                if($line_num == 0){
                    $path_string    = $username;
                }else{
                    $path_string    = $path_string.' -> '.$username;
                }
            }
            if($line_num > 0){
                return $username." (".$path_string.")";
            }else{
                return $username;
            }
        }else{
            return __("orphaned");
        }
    }

    private function _add_nas_realm($nas_id,$realm_id){
        $d                          = array();
        $d['NaRealm']['id']         = '';
        $d['NaRealm']['na_id']      = $nas_id;
        $d['NaRealm']['realm_id']   = $realm_id;

        $this->Na->NaRealm->create();
        $this->Na->NaRealm->save($d);
        $this->Na->NaRealm->id      = false;
    }

    private function _add_nas_tag($nas_id,$tag_id){
        //Delete any previous tags if there were any
        $this->Na->NaTag->deleteAll(array('NaTag.na_id' => $nas_id,'NaTag.tag_id' => $tag_id), false);
        $d                      = array();
        $d['NaTag']['id']       = '';
        $d['NaTag']['na_id']    = $nas_id;
        $d['NaTag']['tag_id']   = $tag_id;
        $this->Na->NaTag->create();
        $this->Na->NaTag->save($d);
        $this->Na->NaTag->id    = false;
    }

    private function _get_action_flags($owner_id,$user){
        if($user['group_name'] == Configure::read('group.admin')){  //Admin
            return array('update' => true, 'delete' => true ,'manage_tags' => true);
        }

        if($user['group_name'] == Configure::read('group.ap')){  //AP
            $user_id = $user['id'];

            //test for self
            if($owner_id == $user_id){
                return array('update' => true, 'delete' => true ,'manage_tags' => true);
            }
            //Test for Parents
            foreach($this->parents as $i){
                if($i['User']['id'] == $owner_id){
                    return array('update' => false, 'delete' => false ,'manage_tags' => false);
                }
            }

            //Test for Children
            foreach($this->children as $i){
                if($i['User']['id'] == $owner_id){
                    return array('update' => true, 'delete' => true ,'manage_tags' => true);
                }
            }  
        }
    }

    function _build_common_query($user){

        //Empty to start with
        $c                  = array();
        $c['joins']         = array(); 
        $c['conditions']    = array();

        //What should we include....
        $c['contain']   = array(
                            'NaRealm'   => array('Realm.name','Realm.id','Realm.available_to_siblings','Realm.user_id'),
                            'NaTag'     => array('Tag.name','Tag.id','Tag.available_to_siblings','Tag.user_id'),
                            'NaNote'    => array('Note.note','Note.id','Note.available_to_siblings','Note.user_id'),
                            'User'
                        );

        //===== SORT =====
        //Default values for sort and dir
        $sort   = 'nasname';
        $dir    = 'DESC';

        if(isset($this->request->query['sort'])){
            if($this->request->query['sort'] == 'owner'){
                $sort = 'User.username';
            }else{
                $sort = $this->modelClass.'.'.$this->request->query['sort'];
            }
            $dir  = $this->request->query['dir'];
        } 
        $c['order'] = array("$sort $dir");
        //==== END SORT ===


        //====== REQUEST FILTER =====
        if(isset($this->request->query['filter'])){
            $filter = json_decode($this->request->query['filter']);
            foreach($filter as $f){
                //Lists
                if($f->type == 'list'){

                    //The tags field has to be treated specially
                    if($f->field == 'tags'){

                        $list_array = array();
                        foreach($f->value as $filter_list){
                            $col = 'Tag.name';
                            array_push($list_array,array("$col" => "$filter_list"));
                        }
                        array_push($c['joins'],array(
                            'table'         => 'na_tags',
                            'alias'         => 'NaTag',
                            'type'          => 'INNER',
                            'conditions'    => array('NaTag.na_id = Na.id')
                        ));
                        array_push($c['joins'],array(
                            'table'         => 'tags',
                            'alias'         => 'Tag',
                            'type'          => 'INNER',
                            'conditions'    => array('Tag.id = NaTag.tag_id',array('OR' => $list_array))
                        ));

                    }elseif($f->field == 'realms'){
                        $list_array = array();
                        foreach($f->value as $filter_list){
                            $col = 'Realm.name';
                            array_push($list_array,array("$col" => "$filter_list"));
                        }
                        array_push($c['joins'],array(
                            'table'         => 'na_realms',
                            'alias'         => 'NaRealm',
                            'type'          => 'INNER',
                            'conditions'    => array('NaRealm.na_id = Na.id')
                        ));
                        array_push($c['joins'],array(
                            'table'         => 'realms',
                            'alias'         => 'Realm',
                            'type'          => 'INNER',
                            'conditions'    => array('Realm.id = NaRealm.realm_id',array('OR' => $list_array))
                        ));                     
                    }else{
                        $list_array = array();
                        foreach($f->value as $filter_list){
                            $col = $this->modelClass.'.'.$f->field;
                            array_push($list_array,array("$col" => "$filter_list"));
                        }
                        //Add it as an OR condition
                        array_push($c['conditions'],array('OR' => $list_array));
                    }
                }
                //Strings
                if($f->type == 'string'){
                    if($f->field == 'owner'){
                        array_push($c['conditions'],array("User.username LIKE" => '%'.$f->value.'%'));   
                    }else{
                        $col = $this->modelClass.'.'.$f->field;
                        array_push($c['conditions'],array("$col LIKE" => '%'.$f->value.'%'));
                    }
                }
                //Bools
                if($f->type == 'boolean'){
                     $col = $this->modelClass.'.'.$f->field;
                     array_push($c['conditions'],array("$col" => $f->value));
                }
            }
        }
        //====== END REQUEST FILTER =====

        //====== AP FILTER =====
        //If the user is an AP; we need to add an extra clause to only show the NAS devices which he is allowed to see.
        if($user['group_name'] == Configure::read('group.ap')){  //AP
            $tree_array = array();
            $user_id    = $user['id'];

            //**AP and upward in the tree**
            $this->parents = $this->User->getPath($user_id,'User.id');
            //So we loop this results asking for the parent nodes who have available_to_siblings = true
            foreach($this->parents as $i){
                $i_id = $i['User']['id'];
                if($i_id != $user_id){ //upstream
                    array_push($tree_array,array('Na.user_id' => $i_id,'Na.available_to_siblings' => true));
                }else{
                    array_push($tree_array,array('Na.user_id' => $i_id));
                }
            }
            //** ALL the AP's children
            $ap_children    = $this->User->find_access_provider_children($user['id']);
            if($ap_children){   //Only if the AP has any children...
                foreach($ap_children as $i){
                    $id = $i['id'];
                    array_push($tree_array,array($this->modelClass.'.user_id' => $id));
                }       
            }    
            //Add it as an OR clause
            array_push($c['conditions'],array('OR' => $tree_array));  
        }       
        //====== END AP FILTER =====      
        return $c;
    }

    private function _is_sibling_of($parent_id,$user_id){
        $this->User->contain();//No dependencies
        $q_r        = $this->User->getPath($user_id);
        foreach($q_r as $i){
            $id = $i['User']['id'];
            if($id == $parent_id){
                return true;
            }
        }
        //No match
        return false;
    }

}

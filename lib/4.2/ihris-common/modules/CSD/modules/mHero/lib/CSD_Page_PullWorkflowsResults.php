<?php
  /*
   * we can call as 
   *      index.php --page=/mHero/pull_workflows_results'  --form=csd_provider
   * to attach results to provider form
   */ 

class CSD_Page_pullWorkflowsResults extends CSD_Page_RapidPro_Base {

    protected $form = false;

    public function __construct($args,$request_remainder, $get=null, $post = null) {
        parent::__construct($args,$request_remainder, $get,$post);
        $has_person = I2CE_ModuleFactory::instance()->isEnabled('Person-CSD');
        $has_provider = I2CE_ModuleFactory::instance()->isEnabled('csd-provider-data-model');
	if ($has_person) {
	    $this->form = 'person';
	} else if ($has_provider) {
	    $this->form = 'csd_provider';
	}
    }



    protected function actionCommandLine($args, $request_remainder) { 
	$this->action();
	if (array_key_exists('form',$args)) {
	    $this->form = $args['form'];   
	}
    }

    protected function action() {
	$target_ids=I2CE_FormStorage::search($this->form);
	$flows = $this->rapidpro->getFlows();
	foreach($target_ids as $id) {
	    $this->factory = I2CE_FormFactory::instance();
	    $targetObj=$this->factory->createContainer($this->form . "|".$id);
	    $targetObj->populate();
	    $count++;
	    echo "\n==================================Processing ".$count."/".count($target_ids)." " . $this->form . "|".$id."=====================================\n";
	    if (!($csd_uuid_field = $targetObj->getField('csd_uuid')) instanceof I2CE_FormField
		|| ! ($csd_uuid = $csd_uuid_field->getValue())
	    	) {
		$this->error("Invalid {$this->form}");
		$targetObj->cleanup();
		continue;
	    }
	    $csd_uuid = 'urn:uuid:' . $csd_uuid;
	    $assigning_authority =  $this->server_host . '/' . $this->slug;
	    if (!($contact = $this->rapidpro->lookupOtherID($this->csd_host, $csd_uuid,$assigning_authority))) {
		$this->error("No contact found for rapidpro in hwr with entity ID $csd_uuid");
		continue;
	    }
	    foreach($flows as $flow_data) {
		//if($this->request("flow")!="all" and $flow_data["flow"]!=$this->request("flow"))
		//continue;
		$this->rapidpro->saveFlowValues($flow_data["flow"],$contact,$targetObj);
	    }
	    $targetObj->cleanup();
	}
	$this->userMessage("Workflows Results Generated");
	$this->setRedirect("mHero");
    }
  }
?>

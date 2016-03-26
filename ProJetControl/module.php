<?
if(!function_exists('IPS_GetInfo')){
	function IPS_GetInfo($ObjectId){
		return ($var=@IPS_GetObject($ObjectId))?$var['ObjectInfo']:false;
	}
}

class ProJetEx extends IPSModule {
	public function Create(){
		parent::Create();
		$this->RegisterPropertyInteger('ProJetDevice', 0);
		$this->RegisterPropertyBoolean('DimState', true);
	}
	public function ApplyChanges(){
		parent::ApplyChanges();
		$this->RegisterVariableBoolean('STATE','Status',"~Switch");
		$this->RegisterVariableInteger('BRIGHTNESS','Helligkeit',"~Intensity.100");
		$this->EnableAction('STATE');
		$this->EnableAction('BRIGHTNESS');
		$this->UpdateLinks();
	}
	public function UpdateLinks(){
		if($DeviceID=$this->ReadPropertyInteger('ProJetDevice')){
			if($id=@IPS_GetObjectIdByIdent('Color',$DeviceID)){
				$this->CreateLink('Color', "Farbe", $id); 			
			}else{
				$this->DeleteLink('Color');
				if($id=@IPS_GetObjectIdByIdent('ChannelR',$DeviceID)){
					$this->CreateLink('ChannelB','Blau',IPS_GetObjectIdByIdent('ChannelB',$DeviceID));
					$this->CreateLink('ChannelG','Grün',IPS_GetObjectIdByIdent('ChannelG',$DeviceID));
					$this->CreateLink('ChannelR','Rot',$id);
				}else{
					$this->DeleteLink('ChannelR');
					$this->DeleteLink('ChannelG');
					$this->DeleteLink('ChannelB');
				}	
			}	
		}else{
			$this->DeleteLink('Color');
			$this->DeleteLink('ChannelR');
			$this->DeleteLink('ChannelG');
			$this->DeleteLink('ChannelB');
		}	
	}	
	public function UpdateColor(){
		if(!$this->ReadPropertyInteger('ProJetDevice'))die('Invalid or no ProJetDevice selected!');
		$DeviceColor=$this->DeviceColor();
//IPS_LOGMessage(__CLASS__.'::'.__FUNCTION__,"Color: $DeviceColor");
		$this->_UpdateColor($DeviceColor,false);
	}	
	public function RequestAction($Ident, $Value) {
		switch ($Ident){
			case 'STATE'	: $this->SetState($Value);break;
			case 'BRIGHTNESS': $this->SetBrightness($Value);break;
			default 		: break;
		}	
	}
	public function SetState(boolean $NewState){
		if(!$this->ReadPropertyInteger('ProJetDevice'))die('Invalid or no ProJetDevice selected!');
		$ID = $this->GetIDForIdent('STATE');
		$OldState = GetValue($ID);
		$dimState = $this->ReadPropertyInteger('DimState');
		if( $OldState != $NewState){
			if($dimState)$deviceId=$this->ReadPropertyInteger('ProJetDevice');
			if($OldState){
				$DeviceColor = $this->DeviceColor();
				IPS_SetInfo($ID, serialize($DeviceColor));
				if($dimState){
					PJ_DimRGBW($deviceId, 0,2, 0,2, 0,2,0,0);
					self::_UpdateColor(array(0,0,0),false);
				} else $DeviceColor=0;
			}else {
				if(!$DeviceColor=unserialize(IPS_GetInfo($ID)))
					$DeviceColor=8355711;
				if($dimState){
					$rgb=self::Int2Rgb($DeviceColor);
					PJ_DimRGBW($deviceId, $rgb[0],2, $rgb[1],2, $rgb[2],2,0,0);
					self::_UpdateColor($rgb,false);
				}	
			}
			if(!$dimState)self::_UpdateColor($DeviceColor);
		}	
	}
	public function SetBrightness(integer $NewBrightness){
		if(!$this->ReadPropertyInteger('ProJetDevice'))die('Invalid or no ProJetDevice selected!');
		$ID = $this->GetIDForIdent('BRIGHTNESS');
		$OldBrightness = GetValue($ID);
		if($OldBrightness != $NewBrightness){
			if(!$OldBrightness){
				$ID = $this->GetIDForIdent('STATE');
				if(!$DeviceColor=unserialize(IPS_GetInfo($ID)))
					$DeviceColor=8355711;
				
			}else	
				$DeviceColor = self::DeviceColor();
			$NewDeviceColor = self::CalcColor($DeviceColor, $OldBrightness, $NewBrightness);
			self::_UpdateColor($NewDeviceColor);
		}
	}
	protected function CreateLink($Ident, $Name, $Target=0){
		if(!$LinkID=@IPS_GetObjectIdByIdent($Ident,$this->InstanceID)){
			$LinkID = IPS_CreateLink();  
			IPS_SetName($LinkID, $Name);
			IPS_SetParent($LinkID, $this->InstanceID);
			IPS_SetIdent($LinkID,$Ident);
			if($Target){
				IPS_SetLinkTargetID($LinkID, $Target);
				$EventID=IPS_CreateEvent(0);
				IPS_SetParent($EventID,$this->InstanceID);// $LinkID);
				IPS_SetEventTrigger($EventID,1, $Target);
				IPS_SetEventScript($EventID,'PJ_UpdateColor($_IPS[\'TARGET\']);');
			}	
		}
	}
	protected function DeleteLink($Ident){
		if($LinkID=@IPS_GetObjectIdByIdent($Ident,$this->InstanceID))
			IPS_DeleteLink($LinkID);  
	}

	private function _UpdateColor($color, $force=true){
		$rgb=is_array($color)?$color:self::Int2Rgb($color);
		$identsity = round( max($rgb[0],$rgb[1],$rgb[2])/2.55);
		if(!$force||PJ_SetRGBW($this->ReadPropertyInteger('ProJetDevice'), $rgb[0],$rgb[1],$rgb[2],0)){
			$this->SetValue('STATE', $identsity>0);
			$this->SetValue('BRIGHTNESS',$identsity);
		}
	}
	private function DeviceColor(){
		$DeviceID = $this->ReadPropertyInteger('ProJetDevice');
		if($ID=IPS_GetObjectIdByIdent('Color',$DeviceID)){
			return GetValue($ID);
		}elseif($ID=IPS_GetObjectIdByIdent('ChannelR',$DeviceID)){
			return array(
				GetValue($ID),
				GetValue(IPS_GetObjectIdByIdent('ChannelG',$DeviceID)),
				GetValue(IPS_GetObjectIdByIdent('ChannelB',$DeviceID))
			);
		}	
		return 0;
	}
	private function Int2Rgb($int){
		$h=dechex($int);while(strlen($h)<6)$h='0'.$h;
		$a=array(hexdec(substr($h,0,2)),hexdec(substr($h,2,2)),hexdec(substr($h,4,2)));
		$a[3]=max($a[0],$a[1],$a[2]);
		return $a;
	}
	private function CalcColor($color, $OldValue, $NewValue){
		$rgb=is_array($color)?$color:self::Int2Rgb($color);
		if(!$OldValue)$OldValue= round( max($rgb[0],$rgb[1],$rgb[2])/2.55);
		$r=round(($rgb[0]/$OldValue)*$NewValue);
		$g=round(($rgb[1]/$OldValue)*$NewValue);
		$b=round(($rgb[2]/$OldValue)*$NewValue);
		if($r>255||$g>255||$b>255){if($r&&$r>1)$r--;
		if($g&&$g>1)$g--;if($b&&$b>1)$b--;}
		return array($r,$g,$b,max($r,$g,$b));
	}	

	protected function SetValue($Ident, $Value){
		if ($ID = $this->GetIDForIdent($Ident)){
			if (GetValue($ID) != $Value)
				SetValue($ID, $Value);
		}	
	}	
	
}
?>
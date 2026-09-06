<?php
declare(strict_types=1);
namespace App\Providers\Adapters;
use Illuminate\Support\Facades\Http;
final class PasarGuardProviderAdapter implements ProviderAdapterInterface {
    private string $base;
    private string $host;
    private int $port;
    public function __construct(string $base, private readonly string $apiKey) {
        $url=parse_url($base);
        if(!$url||($url['scheme']??'')!=='https'||empty($url['host'])||isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])||!in_array($url['path']??'',['','/'],true))throw new ProviderException('invalid_provider_url');
        $this->host=$url['host'];$this->port=$url['port']??443;$this->base=rtrim($base,'/');
    }
    private function username(string $username): string {
        if(!preg_match('/^[a-z0-9_]{3,32}$/D',$username))throw new ProviderException('invalid_username');
        return $username;
    }
    private function request(string $method,string $path,array $data=[]): array {
        $ips=filter_var($this->host,FILTER_VALIDATE_IP)?[$this->host]:gethostbynamel($this->host);
        if(!$ips)throw new ProviderException('dns_failed');
        foreach($ips as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new ProviderException('private_provider_address');
        try {
            $response=Http::acceptJson()->withHeaders(['X-Api-Key'=>$this->apiKey])->connectTimeout(5)->timeout(15)
                ->withOptions(['allow_redirects'=>false,'curl'=>[CURLOPT_RESOLVE=>[$this->host.':'.$this->port.':'.$ips[0]]]])
                ->send($method,$this->base.$path, $method==='GET'?['query'=>$data]:['json'=>$data]);
        } catch(\Throwable) {throw new ProviderException('transport_failed');}
        if(!$response->successful())throw new ProviderException(match($response->status()){401,403=>'auth_or_permission_failed',404=>'not_found',409=>'already_exists',default=>'http_failed'},$response->status());
        $decoded=$response->json();
        if($decoded===null && $method==='DELETE')return [];
        if(!is_array($decoded))throw new ProviderException('invalid_json');
        return $decoded;
    }
    public function readUser(string $username): array {return $this->request('GET','/api/user/'.$this->username($username));}
    public function createUser(string $username,array $groupIds,int $dataLimit,int $expire): array {
        if($dataLimit<0||$expire<0||!$groupIds)throw new ProviderException('invalid_create_parameters');
        foreach($groupIds as $id)if(!is_int($id)||$id<1)throw new ProviderException('invalid_group');
        return $this->request('POST','/api/user',['username'=>$this->username($username),'group_ids'=>$groupIds,'data_limit'=>$dataLimit,'expire'=>$expire,'data_limit_reset_strategy'=>'no_reset']);
    }
    public function modifyUser(string $username,array $changes): array {
        if(array_diff(array_keys($changes),['data_limit','expire','status','group_ids','note']))throw new ProviderException('unsupported_change');
        return $this->request('PUT','/api/user/'.$this->username($username),$changes);
    }
    public function setDisabled(string $username,bool $disabled): array {return $this->request('PUT','/api/user/'.$this->username($username).'/disabled',['disabled'=>$disabled]);}
    public function deleteUser(string $username): void {$this->request('DELETE','/api/user/'.$this->username($username));}
    public function resetUsage(string $username): array {return $this->request('POST','/api/user/'.$this->username($username).'/reset');}
    public function configurations(int $userId): array {
        if($userId<1)throw new ProviderException('invalid_user_id');
        $result=$this->request('GET','/api/user/'.$userId.'/subscription/xray');
        if(!array_is_list($result))throw new ProviderException('expected_config_array');
        return $result;
    }
    public function listUsers(int $offset=0,int $limit=25): array {
        if($offset<0||$limit<1||$limit>100)throw new ProviderException('invalid_pagination');
        return $this->request('GET','/api/users',['offset'=>$offset,'limit'=>$limit]);
    }
}

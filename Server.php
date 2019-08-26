<?php


class server {

	private $server   = null;
	private $host     = '0.0.0.0';
	private $port     = '9501';
	private $taskName = 'swooleUploader';
	private $options  = [
		'work_num' => 4,//worker进程数,一般设置为CPU数的1-4倍
		'daemonize' => false, //是否启用守护进程
		'dispatch_mode' => 1, //数据包分发策略,1-轮询模式
	];

	public function __construct(){
		$this->server = new Swoole\WebSocket\Server($this->host, $this->port);
		$this->server->set($this->options);
		$this->server->on('open', [$this, 'onOpen']);
		$this->server->on('message', [$this, 'onMessage']);
		$this->server->on('close', [$this, 'onClose']);
		$this->server->start();
	}

	public function onOpen($server, $request){
		cli_set_process_title($this->taskName);
		echo "server: handshake success with fd{$request->fd}\n";
		$msg = [
			'msg' => 'connect success'
		];
		$server->push($request->fd, json_encode($msg));
	}

	public function onMessage($server, $frame){
		$opcode = $frame->opcode;
		if ($opcode == 0x08) {
			echo "Close frame received: Code {$frame->code} Reason {$frame->reason}\n";
		}else if($opcode == 0x1){
			echo "Text string\n";
		}else if($opcode == 0x2){
			echo "binary data \n";
		}else{
			echo "Message received: {$frame->data}\n";
		}

		$update_path = 'upload/';
		$data        = json_decode($frame->data, 1);
		$exe         = str_replace('/', '.', strstr(strstr($data['data'], ';', TRUE), '/'));
		$exe         = ($exe == '.jpeg') ? '.jpg' : $exe;
		$tmp         = base64_decode(substr(strstr($data['data'], ','), 1));
		$path        = $update_path . md5(rand(1000, 999)) . $exe;
		file_put_contents($path, $tmp);
		echo "file path: {$path}\n";
		$msg = [
			'msg' => 'upload success'
		];
		$server->push($frame->fd, json_encode($msg));
	}	

	public function onClose($server, $fd){
		echo "client {$fd} closed\n";
        foreach ($server->connections as $newFd) {
            $server->push($newFd, $fd. '已断开！');
        }
	}
}

new server();


//___ Modules used ____
var http    = require('http');
var url     = require('url');     
var io      = require('socket.io');
var fs      = require('fs');
var spawn   = require('child_process').spawn;
var mysql   = require('mysql');

//___ Variable decleration ____
var filename= "/usr/local/var/log/radius/radius.log";
var port    = 8000;

 
//___ Socket.IO ____ 
var server  = http.createServer(function(req, res){});
server.listen(port, '0.0.0.0');
 var io     = io.listen(server);

//___ *We need to do authorization*
io.configure(function (){

  io.set('log level', 3);   //Prod = 1 ; Dev = 3
  io.set('authorization', function (handshakeData, callback) {
    if(handshakeData.query.token == undefined){
        callback(null, false); 
    }else{

        var token       = handshakeData.query.token;
        var connection  = mysql.createConnection({
            host     : 'localhost',
            user     : 'rd',
            password : 'rd',
            database : 'rd'
        });
        connection.connect();

        connection.query('SELECT count(username) AS count FROM users WHERE username=\'root\' AND token= ?',[token], function(err, results) {
            var count = results[0].count;
            if(count == 1){
                callback(null, true); //Valid token for root
            }else{
                callback(null, false); //Not a valid token for root
            }
            //Query done; disconnect;
            connection.end(function(err) {
                // The connection is terminated now
            });
        });  
    }
  });
});

//___ Feed it the log file if it pass the authorization ____
io.on('connection', function(client){

    console.log('Client connected');
    var tail = spawn("tail", ["-f", filename]);
    //client.send( { filename : filename } );
 
    tail.stdout.on("data", function (data) {
        console.log(data.toString('utf-8')); //Show what is sent to client
        client.send( data.toString('utf-8')  )
    });
});

console.log("Up and running on port "+port);

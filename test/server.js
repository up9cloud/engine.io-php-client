const debug = require('debug')(__filename)
const engine = require('engine.io')
const config = require('./config.json')
const server = engine.listen(config.port)
debug('server listen on %d', config.port)
server.on('connection', function (socket) {
  debug('Socket (%s) connected', socket.id)
  socket.send('helloworld')
  socket.on('message', function (data) {
    debug('Message received: %s', data)
    socket.send(data)
  })
  socket.on('close', function () {
    debug('Socket (%s) closed', socket.id)
  })
})

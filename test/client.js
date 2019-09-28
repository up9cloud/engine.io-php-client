const debug = require('debug')(__filename)
const config = require('./config.json')
if (process.argv[2]) {
  debug('change host from %s to %s', config.host, process.argv[2])
  config.host = process.argv[2]
}
const socket = require('engine.io-client')(`ws://${config.host}:${config.port}`)
socket.on('open', function () {
  debug('connected')
  socket.send('from client')
  socket.on('message', function (data) {
    debug('data', data)
  })
  socket.on('close', function () {
    debug('closed')
  })
})

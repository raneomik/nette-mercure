TODO
=================

- [x] ~~Leverage confusions by separating clearly the active publish/broadcast from the passive subscribe/consume and discovery parts~~
- [x] ~~Cleanup & refactor codebase and tests & remove unnecessary parts (eg. BroadcastContext related)~~
- [ ] Enhance tests & codebase
  - [x] ~~Add functional smoke test to check correct service integration, as Integration tests seems insufficient~~
  - [x] ~~Add mutation testing - Infection~~
  - [ ] Rework tests & codebase to augment mutation score
- [x] ~~Provide a subscribing service (add discovery Link header, JWT and auth cookie generations, ...)~~
- [x] ~~Add more documentation & examples (eg. subscribing endpoint setup, turbo streams,...) to README.md~~
- [ ] Enhance Tracy panel
  -  [ ] Add "received messages" using event source & subscriber for each hub
  -  [ ] Add config (discovery) & auth data type (cookie, jwt)
- [ ] Integration examples & demos
    - [ ] Shared TODO list/"document" editor, with simple eventsource
    - [ ] Chat, with [turbostreams](https://turbo.hotwired.dev) (eg. [Symfony ux](https://ux.symfony.com/turbo/test/the/🐑#turbo-streams))
    - [ ] ? Shared Tic Tac Toe, with lobby ?
    - [ ] ? Shared [Fifteen](https://github.com/nette-examples/fifteen), with lobby ? (relevant ? seems singleplayer only)
    - [ ] More ?

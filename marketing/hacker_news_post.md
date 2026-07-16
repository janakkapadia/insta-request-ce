# Hacker News Launch Post (Show HN)

**Title:** Show HN: Insta Request – A real-time, lightweight Postman alternative

**Body:**

Hi HN,

I’m the creator of Insta Request. For the last few years, I’ve watched API testing tools turn from lightweight utilities into heavy, 1GB Electron behemoths that force you into their closed cloud ecosystems. 

I decided to build something different. Insta Request is an API collaboration and testing tool built to be extremely fast, native-feeling, and deeply respectful of your data. 

**Under the hood:**
- **Tech Stack:** It's built on Laravel 13, Vue 3, and Inertia.js. No heavy SPA boilerplate, just clean server-driven state combined with instant client-side reactivity.
- **Real-Time Collaboration:** We use Laravel Reverb (WebSockets) to sync state across your team. If someone edits an environment variable or tweaks a request body, your UI updates instantly without polling.
- **Security First:** Environment variables and API secrets aren't just sitting in plaintext in a database. They are AES-256 encrypted on the fly via custom Eloquent attribute casting.
- **Prunable History:** It keeps a granular execution history of every request, which auto-prunes after 30 days to keep the database light.

It features everything you expect: collections, folders, environments, pre/post-request scripting, and import/export capabilities. 

I’m currently rolling this out to early users and would love your unvarnished technical feedback. What's the one feature keeping you locked into your current API tool? 

Happy to answer any questions about the stack, the WebSocket implementation, or the architecture!

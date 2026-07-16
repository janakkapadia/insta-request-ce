# Social Media Campaigns

## 🧵 Twitter / X Thread

**Tweet 1:**
Postman used to be my favorite tool. Then it became a 1GB Electron beast that forces me to sync my company's secrets to their cloud. 📉
So I built an alternative.
Meet Insta Request 🚀: A blazingly fast, real-time API collaboration tool built for modern teams.
[Link] [Screenshot of UI]

**Tweet 2:**
⚡️ Speed and UX are everything. 
Insta Request isn't bogged down by heavy client-side boilerplate. It’s built on Vue 3 and Laravel 13 to give you a native-feeling request builder that opens instantly.
No loading spinners. Just your APIs.

**Tweet 3:**
🤝 Real-time Collaboration out of the box.
Ever asked a coworker "Hey, did you update the staging API key?"
With Insta Request's WebSocket integration (Laravel Reverb), your UI updates instantly when a teammate modifies an environment variable or edits a request.

**Tweet 4:**
🔒 Security First.
We don't leave your API keys sitting in plaintext. All environment variables in Insta Request are AES-256 encrypted on the fly. 
Your secrets stay secret.

**Tweet 5:**
📦 Easy Migration.
Already have 500 requests in your current tool? Insta Request supports importing your existing collections and environments so your team can switch in minutes.
Try it out here: [Link]

---

## 💼 LinkedIn Post

**Audience:** Engineering Managers, CTOs, Lead Developers.

I’m thrilled to announce Insta Request—a modern, lightweight alternative to traditional API testing tools.

Over the last few years, the standard tools our engineering teams rely on have become bloated and restrictive. I wanted a solution that prioritized developer experience, speed, and real-time collaboration.

Insta Request features:
🔹 Real-time WebSockets to keep your whole team in sync instantly.
🔹 Encrypted environment variables for enterprise-grade security.
🔹 A clean, native-feeling UI built on Vue 3 and Tailwind.
🔹 Seamless import/export of your existing collections.

If your engineering team is tired of slow, heavy API clients, give Insta Request a spin. I'd love to hear your feedback on the architecture and UX!

[Link to landing page]
[High-res Video/Screenshot]

---

## 👽 Reddit Posts

### Subreddit: r/webdev or r/programming
**Title:** I got tired of Postman's bloat, so I built a real-time, lightweight alternative using Vue 3 and Laravel.

**Body:**
Hey everyone,
Like many developers, I’ve grown frustrated with how heavy and closed-off the major API clients have become. It feels like every update makes them slower.
I spent the last few months building **Insta Request**. It’s focused entirely on speed, clean UI, and real-time team collaboration. 

Instead of polling, it uses WebSockets so if someone on your team updates a request or an environment variable, it updates instantly on your screen. It also encrypts all environment variables using AES-256 by default.

I'd love for you to try it out and give me harsh feedback. What do you hate about your current API tool?

### Subreddit: r/laravel
**Title:** Show: A Postman alternative built on Laravel 13, Reverb, and Vue 3

**Body:**
Hey artisans! I wanted to share a project I built to solve my own API testing headaches: **Insta Request**.
It leverages the latest Laravel stack:
- **Laravel 13** for the core engine.
- **Reverb** for real-time WebSockets (syncing collections and environments across team members instantly).
- **Inertia + Vue 3** for the frontend, heavily utilizing Tailwind v4.
- Custom Eloquent casting for AES-256 encryption on all sensitive API keys/environments.
- Pest for testing.

If you're curious about how I structured the domains or handled the WebSocket events, let me know! Happy to open-source some of the design patterns if there's interest.

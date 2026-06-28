<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Terminal, Play, Copy, Check, ChevronDown, Sparkles,
    Settings, Shield, Code2, Globe, Cpu, Loader2
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import MarketingLayout from '@/layouts/MarketingLayout.vue';

// Interactive builder state
const httpMethod = ref<'GET' | 'POST' | 'PUT' | 'DELETE'>('GET');
const targetPath = ref('/v1/payments/subscriptions');
const selectedLanguage = ref<'curl' | 'js' | 'python' | 'go' | 'java'>('curl');
const copiedSnippet = ref(false);

const fullUrl = computed(() => {
    return `https://api.jackman.dev${targetPath.value}`;
});

// Dynamic Code snippets depending on the selected method & language
const codeSnippets = computed(() => {
    const method = httpMethod.value;
    const path = targetPath.value;
    const url = fullUrl.value;

    return {
        curl: `curl --request ${method} \\
  --url ${url} \\
  --header 'Authorization: Bearer YOUR_TOKEN' \\
  --header 'Content-Type: application/json'${method !== 'GET' ? ` \\
  --data '{
    "plan_id": "pro-monthly",
    "payment_method": "stripe_token"
  }'` : ''}`,

        js: `const options = {
  method: '${method}',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN',
    'Content-Type': 'application/json'
  }${method !== 'GET' ? `,
  body: JSON.stringify({
    plan_id: 'pro-monthly',
    payment_method: 'stripe_token'
  })` : ''}
};

fetch('${url}', options)
  .then(res => res.json())
  .then(json => console.log(json))
  .catch(err => console.error(err));`,

        python: `import requests

url = "${url}"
headers = {
    "Authorization": "Bearer YOUR_TOKEN",
    "Content-Type": "application/json"
}
${method !== 'GET' ? `payload = {
    "plan_id": "pro-monthly",
    "payment_method": "stripe_token"
}
response = requests.request("${method}", url, json=payload, headers=headers)` : `response = requests.request("${method}", url, headers=headers)`}

print(response.json())`,

        go: `package main

import (
	"fmt"
	"strings"
	"net/http"
	"io"
)

func main() {
	url := "${url}"
	${method !== 'GET' ? `payload := strings.NewReader(\`{"plan_id": "pro-monthly", "payment_method": "stripe_token"}\`)
	req, _ := http.NewRequest("${method}", url, payload)` : `req, _ := http.NewRequest("${method}", url, nil)`}

	req.Header.Add("Authorization", "Bearer YOUR_TOKEN")
	req.Header.Add("Content-Type", "application/json")

	res, _ := http.DefaultClient.Do(req)
	defer res.Body.Close()
	body, _ := io.ReadAll(res.Body)

	fmt.Println(string(body))
}`,

        java: `import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;

public class InstaRequestRequest {
    public static void main(String[] args) throws Exception {
        HttpClient client = HttpClient.newHttpClient();
        HttpRequest request = HttpRequest.newBuilder()
            .uri(URI.create("${url}"))
            .header("Authorization", "Bearer YOUR_TOKEN")
            .header("Content-Type", "application/json")
            .${method}(${method !== 'GET' ? `HttpRequest.BodyPublishers.ofString("{\\"plan_id\\": \\"pro-monthly\\", \\"payment_method\\": \\"stripe_token\\"}")` : 'HttpRequest.BodyPublishers.noBody()'})
            .build();

        HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());
        System.out.println(response.body());
    }
}`
    };
});

const handleCopySnippet = () => {
    navigator.clipboard.writeText(codeSnippets.value[selectedLanguage.value]);
    copiedSnippet.value = true;
    setTimeout(() => {
        copiedSnippet.value = false;
    }, 2000);
};

// JSON-LD Schema
const schemaMarkup = {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "API Request Builder & Client Tool | InstaRequest",
    "description": "Build, execution trace, and generate production code snippets in seconds. InstaRequest provides a modern client for Rest, GraphQL, and high-performance endpoints.",
    "breadcrumb": {
        "@type": "BreadcrumbList",
        "itemListElement": [
            { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://jackman.dev" },
            { "@type": "ListItem", "position": 2, "name": "API Request Builder", "item": "https://jackman.dev/request-builder" }
        ]
    }
};
</script>

<template>
    <MarketingLayout>
        <Head>
            <title>Modern API Request Builder & Execution Client | InstaRequest</title>
            <meta name="description" content="Build and debug APIs with a lightning-fast request client. Instant multi-language code output triggers, environment parameter injection, and beautiful visual parameters grids." />
            <component :is="'script'" type="application/ld+json" v-html="JSON.stringify(schemaMarkup)"></component>
        </Head>

        <!-- Breadcrumbs -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
            <nav class="flex text-3xs font-mono uppercase tracking-wider text-muted-foreground gap-2">
                <Link href="/" class="hover:text-foreground">Home</Link>
                <span>/</span>
                <span class="text-green-500 font-semibold">API Request Builder</span>
            </nav>
        </div>

        <!-- Hero Header -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-16 text-center relative z-10 flex flex-col items-center gap-6">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-green-500/30 text-green-500 text-[11px] font-semibold bg-green-500/5">
                <Terminal class="h-3.5 w-3.5" />
                <span>Zero-latency web-based sandbox</span>
            </div>
            <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-[#1b1b18] dark:text-white max-w-4xl leading-tight">
                A Beautifully Modern <span class="bg-gradient-to-r from-green-500 to-lime-500 bg-clip-text text-transparent">API Request Client</span>
            </h1>
            <p class="text-base sm:text-lg text-muted-foreground leading-relaxed max-w-2xl">
                Create, organize, and compile requests inside a clutter-free pane. Write complex payloads once, verify schemas locally, and instantly convert them to ready-to-run production microservices snippets.
            </p>
        </section>

        <!-- Interactive Playground -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-stretch">
                <!-- Sandbox Controller Settings -->
                <div class="lg:col-span-5 flex flex-col justify-between gap-8 text-left">
                    <div class="flex flex-col gap-6">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-green-500/10 rounded-lg text-green-500">
                                <Sparkles class="h-5 w-5" />
                            </div>
                            <h2 class="text-xl font-bold">Interactive Compiler</h2>
                        </div>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Click HTTP methods below to dynamically alter headers and payload layouts. Select language tabs inside the terminal mockup to view and copy beautifully generated production-ready integration scripts.
                        </p>

                        <!-- Method selector grids -->
                        <div class="flex flex-col gap-3">
                            <span class="text-3xs uppercase font-bold text-muted-foreground tracking-wider font-mono">Select HTTP Method</span>
                            <div class="grid grid-cols-4 gap-2">
                                <button
                                    v-for="m in (['GET', 'POST', 'PUT', 'DELETE'] as const)"
                                    :key="m"
                                    @click="httpMethod = m"
                                    class="py-2.5 rounded-lg border text-2xs font-mono font-bold text-center transition-all"
                                    :class="[
                                        httpMethod === m
                                            ? m === 'GET'
                                                ? 'border-emerald-500/50 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                : m === 'POST'
                                                ? 'border-indigo-500/50 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400'
                                                : m === 'PUT'
                                                ? 'border-amber-500/50 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                : 'border-red-500/50 bg-red-500/10 text-red-600 dark:text-red-400'
                                            : 'border-[#19140010] dark:border-[#222] bg-[#fcfcfa]/50 dark:bg-[#121211]/50 hover:border-green-500/30'
                                    ]"
                                >
                                    {{ m }}
                                </button>
                            </div>
                        </div>

                        <!-- URI route input mockup -->
                        <div class="flex flex-col gap-3">
                            <span class="text-3xs uppercase font-bold text-muted-foreground tracking-wider font-mono">API Path Endpoint</span>
                            <div class="flex items-center gap-2 bg-[#fdfdfb] dark:bg-[#121211] border border-border/80 rounded-lg p-2 font-mono text-3xs">
                                <span class="text-muted-foreground font-semibold">https://api.jackman.dev</span>
                                <input
                                    v-model="targetPath"
                                    type="text"
                                    class="flex-1 bg-transparent focus:outline-none text-foreground font-bold border-l border-border/60 pl-2 focus:text-green-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Sandbox pricing bottom block -->
                    <div class="p-4 bg-[#fcfcfa] dark:bg-[#141413] border border-border/60 rounded-xl flex items-center justify-between gap-4">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-2xs font-bold">100% Web-based sandbox</span>
                            <span class="text-4xs text-muted-foreground">Team workspace upgrades available.</span>
                        </div>
                        <Link href="/register" class="px-3 py-1.5 bg-green-500 text-white rounded text-3xs font-semibold hover:bg-green-600 transition-all font-mono">
                            Get Started
                        </Link>
                    </div>
                </div>

                <!-- Interactive Code snippet UI mockup -->
                <div class="lg:col-span-7 flex flex-col">
                    <div class="bg-white dark:bg-[#121211] border border-border/80 rounded-xl shadow-2xl flex-1 flex flex-col items-stretch text-left relative overflow-hidden">
                        
                        <!-- Top header address panel -->
                        <div class="flex items-center justify-between px-5 py-4 border-b border-border/60 bg-[#fafaf9] dark:bg-[#151514]">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-green-500"></span>
                                <span class="text-3xs font-mono font-bold tracking-wide text-foreground uppercase">InstaRequest Code Generator</span>
                            </div>
                            <button
                                @click="handleCopySnippet"
                                class="px-3 py-1 bg-black/[0.04] dark:bg-white/[0.04] hover:bg-black/10 dark:hover:bg-white/10 rounded text-[10px] font-mono font-semibold flex items-center gap-1.5 border border-border transition-all"
                            >
                                <Copy v-if="!copiedSnippet" class="h-3.5 w-3.5 shrink-0" />
                                <Check v-else class="h-3.5 w-3.5 text-emerald-500 shrink-0" />
                                <span>{{ copiedSnippet ? 'Copied!' : 'Copy Script' }}</span>
                            </button>
                        </div>

                        <!-- Language tabs -->
                        <div class="flex items-center gap-0.5 px-4 bg-[#f8f8f6] dark:bg-[#131312] border-b border-border/40 overflow-x-auto shrink-0">
                            <button
                                v-for="lang in (['curl', 'js', 'python', 'go', 'java'] as const)"
                                :key="lang"
                                @click="selectedLanguage = lang"
                                class="px-3.5 py-3 border-b-2 text-3xs font-mono font-bold transition-all uppercase tracking-wide"
                                :class="[
                                    selectedLanguage === lang
                                        ? 'border-green-500 text-green-500 font-extrabold'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                ]"
                            >
                                {{ lang === 'js' ? 'JavaScript' : lang }}
                            </button>
                        </div>

                        <!-- Code display sandbox -->
                        <div class="flex-1 p-5 bg-[#fafaf9] dark:bg-[#0b0b0a] font-mono text-[10.5px] leading-relaxed text-[#555] dark:text-[#a0a0ab] overflow-auto max-h-[300px] border-b border-border/40">
                            <pre class="whitespace-pre-wrap font-mono">{{ codeSnippets[selectedLanguage] }}</pre>
                        </div>

                        <!-- Mini parameter status indicator footer -->
                        <div class="px-5 py-3.5 bg-white dark:bg-[#121211] text-[9.5px] font-mono text-muted-foreground flex items-center justify-between">
                            <span>HTTP Method: <strong class="text-green-500 font-bold uppercase">{{ httpMethod }}</strong></span>
                            <span>Payload: <strong class="text-[#1b1b18] dark:text-white font-bold">{{ httpMethod === 'GET' ? 'No Request Body' : 'Raw JSON Body (2 params)' }}</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Product Details Columns -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10 border-t border-border/40">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Box 1 -->
                <div class="bg-white dark:bg-[#121211] border border-border/60 rounded-xl p-6 flex flex-col gap-4 text-left shadow-sm">
                    <div class="h-10 w-10 bg-green-500/10 rounded-lg flex items-center justify-center text-green-500">
                        <Code2 class="h-5 w-5" />
                    </div>
                    <h3 class="text-sm font-bold">12+ Target Languages</h3>
                    <p class="text-3xs sm:text-2xs text-muted-foreground leading-relaxed">
                        Instantly compile collections and presets to highly optimized client code for cURL, fetch, requests, Axios, Go http client, Java net client, and more.
                    </p>
                </div>

                <!-- Box 2 -->
                <div class="bg-white dark:bg-[#121211] border border-border/60 rounded-xl p-6 flex flex-col gap-4 text-left shadow-sm">
                    <div class="h-10 w-10 bg-green-500/10 rounded-lg flex items-center justify-center text-green-500">
                        <Globe class="h-5 w-5" />
                    </div>
                    <h3 class="text-sm font-bold">Environment Variables</h3>
                    <p class="text-3xs sm:text-2xs text-muted-foreground leading-relaxed">
                        Pre-inject sandbox environments. Our generator parses standard dynamic variables single-curly syntax (e.g. `{host}`) and outputs them clean.
                    </p>
                </div>

                <!-- Box 3 -->
                <div class="bg-white dark:bg-[#121211] border border-border/60 rounded-xl p-6 flex flex-col gap-4 text-left shadow-sm">
                    <div class="h-10 w-10 bg-green-500/10 rounded-lg flex items-center justify-center text-green-500">
                        <Settings class="h-5 w-5" />
                    </div>
                    <h3 class="text-sm font-bold">Privacy-First Design</h3>
                    <p class="text-3xs sm:text-2xs text-muted-foreground leading-relaxed">
                        InstaRequest works completely in-browser with total variables privacy. No unencrypted requests or API tokens are ever logged to external third-party servers.
                    </p>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>

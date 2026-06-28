<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
</script>

<template>
    <Head title="Terms of Service | Jackman" />
    <MarketingLayout>
        <div class="max-w-4xl mx-auto px-6 py-24 md:py-32">
            <div class="space-y-4 mb-16 text-center">
                <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-[#1b1b18] dark:text-[#ffffff]">
                    Terms of Service
                </h1>
                <p class="text-muted-foreground text-lg max-w-2xl mx-auto">
                    Last updated: June 22, 2026
                </p>
            </div>

            <div class="prose prose-slate dark:prose-invert max-w-none text-muted-foreground">
                <p>Welcome to Jackman. These Terms of Service ("Terms") govern your use of the Jackman collaborative API platform, website, desktop application, and associated services (collectively, the "Service").</p>

                <h2 class="text-xl font-bold text-foreground mt-8 mb-4">1. Acceptance of Terms</h2>
                <p>By accessing or using our Service, you agree to be bound by these Terms and our Privacy Policy. If you do not agree to these Terms, do not use the Service.</p>

                <h2 class="text-xl font-bold text-foreground mt-8 mb-4">2. Use of the Service</h2>
                <p>You must provide accurate information when creating an account. You are responsible for maintaining the security of your account and your API keys, credentials, and tokens used within Jackman.</p>
                
                <h2 class="text-xl font-bold text-foreground mt-8 mb-4">3. Acceptable Use</h2>
                <p>You agree not to use the Service to:</p>
                <ul class="list-disc pl-6 space-y-2 mt-2">
                    <li>Violate any applicable laws or regulations.</li>
                    <li>Perform malicious security testing, DDoS attacks, or unauthorized penetration testing on third-party APIs without explicit permission.</li>
                    <li>Interfere with or disrupt the integrity or performance of the Service.</li>
                    <li>Attempt to gain unauthorized access to the Service or its related systems.</li>
                </ul>

                <h2 class="text-xl font-bold text-foreground mt-8 mb-4">4. Intellectual Property</h2>
                <p>The Service and its original content, features, and functionality are owned by Jackman and are protected by international copyright, trademark, patent, trade secret, and other intellectual property laws.</p>

                <h2 class="text-xl font-bold text-foreground mt-8 mb-4">5. Termination</h2>
                <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach the Terms.</p>

                <h2 class="text-xl font-bold text-foreground mt-8 mb-4">6. Limitation of Liability</h2>
                <p>In no event shall Jackman, nor its directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from your access to or use of or inability to access or use the Service.</p>

                <h2 class="text-xl font-bold text-foreground mt-8 mb-4">7. Changes</h2>
                <p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time. We will provide notice of any significant changes.</p>

                <h2 class="text-xl font-bold text-foreground mt-8 mb-4">8. Contact Us</h2>
                <p>If you have any questions about these Terms, please contact us at support@jackman.dev.</p>
            </div>
        </div>
    </MarketingLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MarketingLayout from '@/layouts/MarketingLayout.vue';
</script>

<template>
    <Head title="Terms of Service | InstaRequest" />
    <MarketingLayout>
        <div class="mx-auto max-w-4xl px-6 py-24 md:py-32">
            <div class="mb-16 space-y-4 text-center">
                <h1
                    class="text-4xl font-extrabold tracking-tight text-[#1b1b18] md:text-5xl dark:text-[#ffffff]"
                >
                    Terms of Service
                </h1>
                <p class="mx-auto max-w-2xl text-lg text-muted-foreground">
                    Last updated: June 22, 2026
                </p>
            </div>

            <div
                class="prose prose-slate dark:prose-invert max-w-none text-muted-foreground"
            >
                <p>
                    Welcome to InstaRequest. These Terms of Service ("Terms")
                    govern your use of the InstaRequest collaborative API
                    platform, website, and associated services (collectively,
                    the "Service").
                </p>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    1. Acceptance of Terms
                </h2>
                <p>
                    By accessing or using our Service, you agree to be bound by
                    these Terms and our Privacy Policy. If you do not agree to
                    these Terms, do not use the Service.
                </p>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    2. Use of the Service
                </h2>
                <p>
                    You must provide accurate information when creating an
                    account. You are responsible for maintaining the security of
                    your account and your API keys, credentials, and tokens used
                    within InstaRequest.
                </p>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    3. Acceptable Use
                </h2>
                <p>You agree not to use the Service to:</p>
                <ul class="mt-2 list-disc space-y-2 pl-6">
                    <li>Violate any applicable laws or regulations.</li>
                    <li>
                        Perform malicious security testing, DDoS attacks, or
                        unauthorized penetration testing on third-party APIs
                        without explicit permission.
                    </li>
                    <li>
                        Interfere with or disrupt the integrity or performance
                        of the Service.
                    </li>
                    <li>
                        Attempt to gain unauthorized access to the Service or
                        its related systems.
                    </li>
                </ul>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    4. Intellectual Property
                </h2>
                <p>
                    The Service and its original content, features, and
                    functionality are owned by InstaRequest and are protected by
                    international copyright, trademark, patent, trade secret,
                    and other intellectual property laws.
                </p>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    5. Termination
                </h2>
                <p>
                    We may terminate or suspend your account immediately,
                    without prior notice or liability, for any reason
                    whatsoever, including without limitation if you breach the
                    Terms.
                </p>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    6. Limitation of Liability
                </h2>
                <p>
                    In no event shall InstaRequest, nor its directors,
                    employees, partners, agents, suppliers, or affiliates, be
                    liable for any indirect, incidental, special, consequential
                    or punitive damages, including without limitation, loss of
                    profits, data, use, goodwill, or other intangible losses,
                    resulting from your access to or use of or inability to
                    access or use the Service.
                </p>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    7. Changes
                </h2>
                <p>
                    We reserve the right, at our sole discretion, to modify or
                    replace these Terms at any time. We will provide notice of
                    any significant changes.
                </p>

                <h2 class="mt-8 mb-4 text-xl font-bold text-foreground">
                    8. Contact Us
                </h2>
                <p>
                    If you have any questions about these Terms, please contact
                    us at support@instarequest.com.
                </p>
            </div>
        </div>
    </MarketingLayout>
</template>

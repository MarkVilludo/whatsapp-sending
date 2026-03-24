<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Messages
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Send WhatsApp Template Message</h3>

                    @if (session('status'))
                        <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->has('send'))
                        <div class="mb-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ $errors->first('send') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('messages.send') }}"
                        class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @csrf

                        <div>
                            <x-input-label for="from" :value="__('From')" />
                            <x-text-input id="from" name="from" type="text" class="mt-1 block w-full"
                                :value="old('from')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('from')" />
                        </div>

                        <div>
                            <x-input-label for="to" :value="__('To')" />
                            <x-text-input id="to" name="to" type="text" class="mt-1 block w-full"
                                :value="old('to')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('to')" />
                        </div>

                        <div class="md:col-span-3">
                            <x-input-label for="message" :value="__('Body Placeholder')" />
                            <textarea id="message" name="message" rows="3"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                required>{{ old('message') }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('message')" />
                        </div>

                        <div class="md:col-span-3">
                            <x-input-label for="media_url" :value="__('Header Image URL (optional)')" />
                            <x-text-input id="media_url" name="media_url" type="url" class="mt-1 block w-full"
                                :value="old('media_url')" />
                            <x-input-error class="mt-2" :messages="$errors->get('media_url')" />
                        </div>

                        <div class="md:col-span-3">
                            <x-primary-button>Send</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" x-data="{ tab: 'outgoing' }">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex items-center gap-3 mb-6">
                        <button type="button" @click="tab = 'outgoing'"
                            :class="tab === 'outgoing' ? 'bg-indigo-600 text-white' :
                                'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200'"
                            class="px-4 py-2 rounded text-sm font-medium">
                            Outgoing
                        </button>
                        <button type="button" @click="tab = 'incoming'"
                            :class="tab === 'incoming' ? 'bg-indigo-600 text-white' :
                                'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200'"
                            class="px-4 py-2 rounded text-sm font-medium">
                            Incoming
                        </button>
                    </div>

                    <div x-show="tab === 'outgoing'" x-cloak>
                        <h3 class="text-lg font-semibold mb-3">Outgoing Messages</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left py-2 pr-4">From</th>
                                        <th class="text-left py-2 pr-4">To</th>
                                        <th class="text-left py-2 pr-4">Message</th>
                                        <th class="text-left py-2 pr-4">Status</th>
                                        <th class="text-left py-2 pr-4">Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($outgoingMessages as $message)
                                        <tr class="border-b border-gray-100 dark:border-gray-700">
                                            <td class="py-2 pr-4">{{ $message->fromNumber?->number }}</td>
                                            <td class="py-2 pr-4">{{ $message->toNumber?->number }}</td>
                                            <td class="py-2 pr-4">{{ $message->message_text }}</td>
                                            <td class="py-2 pr-4">{{ strtoupper($message->status) }}</td>
                                            <td class="py-2 pr-4">
                                                {{ optional($message->sent_at ?? $message->created_at)->format('Y-m-d H:i:s') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-4 text-gray-500">No outgoing messages yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div x-show="tab === 'incoming'" x-cloak>
                        <h3 class="text-lg font-semibold mb-3">Incoming Messages</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left py-2 pr-4">From</th>
                                        <th class="text-left py-2 pr-4">To</th>
                                        <th class="text-left py-2 pr-4">Message</th>
                                        <th class="text-left py-2 pr-4">Status</th>
                                        <th class="text-left py-2 pr-4">Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($incomingMessages as $message)
                                        <tr class="border-b border-gray-100 dark:border-gray-700">
                                            <td class="py-2 pr-4">{{ $message->fromNumber?->number }}</td>
                                            <td class="py-2 pr-4">{{ $message->toNumber?->number }}</td>
                                            <td class="py-2 pr-4">{{ $message->message_text }}</td>
                                            <td class="py-2 pr-4">{{ strtoupper($message->status) }}</td>
                                            <td class="py-2 pr-4">
                                                {{ optional($message->received_at ?? $message->created_at)->format('Y-m-d H:i:s') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-4 text-gray-500">No incoming messages yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

import re

with open("resources/js/pages/Documentation/PublicViewer.vue", "r") as f:
    content = f.read()

# Update Request Body table headers
content = content.replace(
    '''                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Key
                                            </th>
                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Type
                                            </th>
                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Value
                                            </th>''',
    '''                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Key
                                            </th>
                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Type
                                            </th>
                                            <th
                                                class="px-4 py-2 text-left font-semibold text-muted-foreground"
                                            >
                                                Description
                                            </th>'''
)

# Update Request Body table rows
content = content.replace(
    '''                                        <tr
                                            v-for="item in parsedBodyItems"
                                            :key="item.key"
                                        >
                                            <td
                                                class="px-4 py-2 font-mono font-semibold text-foreground"
                                            >
                                                {{ item.key }}
                                            </td>
                                            <td
                                                class="px-4 py-2 font-mono text-[10px] text-muted-foreground"
                                            >
                                                string
                                            </td>
                                            <td
                                                class="px-4 py-2 font-mono text-muted-foreground/80 select-all"
                                            >
                                                {{ item.value }}
                                            </td>
                                        </tr>''',
    '''                                        <tr
                                            v-for="item in parsedBodyItems"
                                            :key="item.key"
                                        >
                                            <td
                                                class="px-4 py-2 font-mono font-semibold text-foreground"
                                            >
                                                {{ item.key }}
                                                <span v-if="item.required" class="ml-1 text-[10px] text-red-500">*</span>
                                                <span v-else class="ml-1 text-[10px] text-muted-foreground">(optional)</span>
                                            </td>
                                            <td
                                                class="px-4 py-2 font-mono text-[10px] text-muted-foreground"
                                            >
                                                {{ item.dataType || 'string' }}
                                            </td>
                                            <td
                                                class="px-4 py-2 font-mono text-muted-foreground/80 select-all"
                                            >
                                                {{ item.description || '' }}
                                            </td>
                                        </tr>'''
)

with open("resources/js/pages/Documentation/PublicViewer.vue", "w") as f:
    f.write(content)

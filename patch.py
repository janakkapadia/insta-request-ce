import re

with open("resources/js/components/RequestEditor/RequestEditor.vue", "r") as f:
    content = f.read()

# Form Data Header
content = content.replace(
    '''                                                    <div
                                                        class="grid h-8 grid-cols-[40px_1.5fr_100px_2fr_2fr_50px] items-center bg-muted/40 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                                    >
                                                        <div></div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Key
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Type
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Value
                                                        </div>''',
    '''                                                    <div
                                                        class="grid h-8 grid-cols-[40px_1.5fr_80px_100px_50px_1.5fr_2fr_50px] items-center bg-muted/40 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                                    >
                                                        <div></div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Key
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Type
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Data Type
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Req
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Value
                                                        </div>'''
)

# Form Data Row
content = content.replace(
    '''                                                    <div
                                                        v-for="(
                                                            item, idx
                                                        ) in bodyConfig.formdata"
                                                        :key="idx"
                                                        class="group/row grid grid-cols-[40px_1.5fr_100px_2fr_2fr_50px] items-center border-b transition-colors last:border-0 hover:bg-muted/10"
                                                    >''',
    '''                                                    <div
                                                        v-for="(
                                                            item, idx
                                                        ) in bodyConfig.formdata"
                                                        :key="idx"
                                                        class="group/row grid grid-cols-[40px_1.5fr_80px_100px_50px_1.5fr_2fr_50px] items-center border-b transition-colors last:border-0 hover:bg-muted/10"
                                                    >'''
)

formdata_type_select = '''                                                        <div
                                                            class="flex h-full items-center border-r px-1"
                                                        >
                                                            <Select
                                                                v-model="
                                                                    item.type
                                                                "
                                                            >
                                                                <SelectTrigger
                                                                    class="h-6 w-full border-0 bg-transparent px-1 text-[11px] shadow-none focus:ring-0"
                                                                >
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem
                                                                        value="text"
                                                                        class="text-xs"
                                                                        >Text</SelectItem
                                                                    >
                                                                    <SelectItem
                                                                        value="file"
                                                                        class="text-xs"
                                                                        >File</SelectItem
                                                                    >
                                                                </SelectContent>
                                                            </Select>
                                                        </div>'''

formdata_new_cols = formdata_type_select + '''
                                                        <div
                                                            class="flex h-full items-center border-r px-1"
                                                        >
                                                            <Select
                                                                v-model="
                                                                    item.dataType
                                                                "
                                                            >
                                                                <SelectTrigger
                                                                    class="h-6 w-full border-0 bg-transparent px-1 text-[11px] shadow-none focus:ring-0"
                                                                >
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="string" class="text-xs">String</SelectItem>
                                                                    <SelectItem value="integer" class="text-xs">Integer</SelectItem>
                                                                    <SelectItem value="boolean" class="text-xs">Boolean</SelectItem>
                                                                    <SelectItem value="file" class="text-xs">File</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div class="flex h-full items-center justify-center border-r">
                                                            <Checkbox
                                                                :model-value="item.required"
                                                                @update:model-value="item.required = !!$event"
                                                            />
                                                        </div>'''

content = content.replace(formdata_type_select, formdata_new_cols)

# URL Encoded Header
content = content.replace(
    '''                                                    <div
                                                        class="grid h-8 grid-cols-[40px_2fr_2fr_2fr_50px] items-center bg-muted/40 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                                    >
                                                        <div></div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Key
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Value
                                                        </div>''',
    '''                                                    <div
                                                        class="grid h-8 grid-cols-[40px_1.5fr_100px_50px_1.5fr_2fr_50px] items-center bg-muted/40 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                                    >
                                                        <div></div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Key
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Data Type
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Req
                                                        </div>
                                                        <div
                                                            class="flex h-full items-center border-r px-2"
                                                        >
                                                            Value
                                                        </div>'''
)

# URL Encoded Row
content = content.replace(
    '''                                                    <div
                                                        v-for="(
                                                            item, idx
                                                        ) in bodyConfig.urlencoded"
                                                        :key="idx"
                                                        class="group/row grid grid-cols-[40px_2fr_2fr_2fr_50px] items-center border-b transition-colors last:border-0 hover:bg-muted/10"
                                                    >''',
    '''                                                    <div
                                                        v-for="(
                                                            item, idx
                                                        ) in bodyConfig.urlencoded"
                                                        :key="idx"
                                                        class="group/row grid grid-cols-[40px_1.5fr_100px_50px_1.5fr_2fr_50px] items-center border-b transition-colors last:border-0 hover:bg-muted/10"
                                                    >'''
)

urlencoded_key_input = '''                                                        <div
                                                            class="flex h-full items-center border-r"
                                                        >
                                                            <VariableInput
                                                                v-model="
                                                                    item.key
                                                                "
                                                                placeholder="Key"
                                                                class="h-8 border-0 bg-transparent px-0 text-xs focus-visible:ring-0 focus-visible:ring-offset-0"
                                                                @input="
                                                                    handleUrlEncodedInput(
                                                                        idx,
                                                                    )
                                                                "
                                                            />
                                                        </div>'''

urlencoded_new_cols = urlencoded_key_input + '''
                                                        <div
                                                            class="flex h-full items-center border-r px-1"
                                                        >
                                                            <Select
                                                                v-model="
                                                                    item.dataType
                                                                "
                                                            >
                                                                <SelectTrigger
                                                                    class="h-6 w-full border-0 bg-transparent px-1 text-[11px] shadow-none focus:ring-0"
                                                                >
                                                                    <SelectValue />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="string" class="text-xs">String</SelectItem>
                                                                    <SelectItem value="integer" class="text-xs">Integer</SelectItem>
                                                                    <SelectItem value="boolean" class="text-xs">Boolean</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div class="flex h-full items-center justify-center border-r">
                                                            <Checkbox
                                                                :model-value="item.required"
                                                                @update:model-value="item.required = !!$event"
                                                            />
                                                        </div>'''

content = content.replace(urlencoded_key_input, urlencoded_new_cols)

with open("resources/js/components/RequestEditor/RequestEditor.vue", "w") as f:
    f.write(content)

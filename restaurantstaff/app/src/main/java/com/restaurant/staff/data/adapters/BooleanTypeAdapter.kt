package com.restaurant.staff.data.adapters

import com.google.gson.TypeAdapter
import com.google.gson.stream.JsonReader
import com.google.gson.stream.JsonWriter
import java.io.IOException

class BooleanTypeAdapter : TypeAdapter<Boolean>() {

    override fun write(out: JsonWriter, value: Boolean?) {
        if (value == null) {
            out.nullValue()
        } else {
            out.value(value)
        }
    }

    override fun read(reader: JsonReader): Boolean? {
        return when (reader.peek()) {
            com.google.gson.stream.JsonToken.NULL -> {
                reader.nextNull()
                null
            }
            com.google.gson.stream.JsonToken.BOOLEAN -> {
                reader.nextBoolean()
            }
            com.google.gson.stream.JsonToken.NUMBER -> {
                val intValue = reader.nextInt()
                intValue == 1
            }
            com.google.gson.stream.JsonToken.STRING -> {
                val stringValue = reader.nextString().lowercase()
                stringValue == "true" || stringValue == "1" || stringValue == "yes"
            }
            else -> {
                reader.skipValue()
                false
            }
        }
    }
}

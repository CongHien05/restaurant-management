package com.restaurant.staff.utils

import android.content.Context
import android.content.SharedPreferences
import com.google.gson.Gson
import com.restaurant.staff.data.model.User

class PreferenceManager(context: Context) {

    private val prefs: SharedPreferences = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
    private val gson = Gson()

    companion object {
        private const val PREF_NAME = "restaurant_staff_prefs"
        private const val KEY_AUTH_TOKEN = "auth_token"
        private const val KEY_USER_INFO = "user_info"
        private const val KEY_IS_LOGGED_IN = "is_logged_in"
        private const val KEY_REMEMBER_LOGIN = "remember_login"
        private const val KEY_LAST_USERNAME = "last_username"
        private const val KEY_APP_FIRST_RUN = "app_first_run"
    }

    // Authentication
    fun saveAuthToken(token: String?) {
        prefs.edit().putString(KEY_AUTH_TOKEN, token).apply()
    }

    fun getAuthToken(): String? {
        return prefs.getString(KEY_AUTH_TOKEN, null)
    }

    fun saveUserInfo(user: User) {
        val userJson = gson.toJson(user)
        prefs.edit()
            .putString(KEY_USER_INFO, userJson)
            .putBoolean(KEY_IS_LOGGED_IN, true)
            .apply()
    }

    fun getUserInfo(): User? {
        val userJson = prefs.getString(KEY_USER_INFO, null)
        return if (userJson != null) {
            try {
                gson.fromJson(userJson, User::class.java)
            } catch (e: Exception) {
                null
            }
        } else {
            null
        }
    }

    fun isLoggedIn(): Boolean {
        return prefs.getBoolean(KEY_IS_LOGGED_IN, false) && getAuthToken() != null
    }

    fun logout() {
        prefs.edit()
            .remove(KEY_AUTH_TOKEN)
            .remove(KEY_USER_INFO)
            .putBoolean(KEY_IS_LOGGED_IN, false)
            .apply()
    }

    // Login preferences
    fun setRememberLogin(remember: Boolean) {
        prefs.edit().putBoolean(KEY_REMEMBER_LOGIN, remember).apply()
    }

    fun shouldRememberLogin(): Boolean {
        return prefs.getBoolean(KEY_REMEMBER_LOGIN, false)
    }

    fun saveLastUsername(username: String) {
        prefs.edit().putString(KEY_LAST_USERNAME, username).apply()
    }

    fun getLastUsername(): String? {
        return prefs.getString(KEY_LAST_USERNAME, null)
    }

    // App state
    fun isFirstRun(): Boolean {
        return prefs.getBoolean(KEY_APP_FIRST_RUN, true)
    }

    fun setFirstRunComplete() {
        prefs.edit().putBoolean(KEY_APP_FIRST_RUN, false).apply()
    }

    // Clear all data
    fun clearAll() {
        prefs.edit().clear().apply()
    }
}


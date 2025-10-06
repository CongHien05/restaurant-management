package com.restaurant.staff.ui.order

import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.restaurant.staff.data.model.MenuItem
import com.restaurant.staff.databinding.ItemMenuOrderBinding

class MenuAdapter(
    private val onItemClick: (MenuItem) -> Unit
) : ListAdapter<MenuItem, MenuAdapter.MenuViewHolder>(MenuDiffCallback()) {

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): MenuViewHolder {
        val binding = ItemMenuOrderBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return MenuViewHolder(binding, onItemClick)
    }

    override fun onBindViewHolder(holder: MenuViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    class MenuViewHolder(
        private val binding: ItemMenuOrderBinding,
        private val onItemClick: (MenuItem) -> Unit
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(menuItem: MenuItem) {
            binding.apply {
                textMenuItemName.text = menuItem.name
                textMenuItemPrice.text = "₫${String.format("%,.0f", menuItem.price)}"
                textMenuItemDescription.text = menuItem.description

                // Load image if available
                if (!menuItem.image.isNullOrEmpty()) {
                    Glide.with(root.context)
                        .load(menuItem.image)
                        .placeholder(com.restaurant.staff.R.drawable.ic_food_placeholder)
                        .error(com.restaurant.staff.R.drawable.ic_food_placeholder)
                        .into(imageMenuItem)
                } else {
                    imageMenuItem.setImageResource(com.restaurant.staff.R.drawable.ic_food_placeholder)
                }

                // Show/hide availability
                if (menuItem.isAvailable) {
                    chipAvailability.text = "Có sẵn"
                    chipAvailability.setChipBackgroundColorResource(com.restaurant.staff.R.color.green_600)
                } else {
                    chipAvailability.text = "Hết hàng"
                    chipAvailability.setChipBackgroundColorResource(com.restaurant.staff.R.color.red_500)
                }

                // Setup click listener
                root.setOnClickListener {
                    if (menuItem.isAvailable) {
                        onItemClick(menuItem)
                        // Show feedback
                        com.google.android.material.snackbar.Snackbar.make(
                            root,
                            "Đã thêm ${menuItem.name}",
                            com.google.android.material.snackbar.Snackbar.LENGTH_SHORT
                        ).show()
                    } else {
                        // Show not available message
                        com.google.android.material.snackbar.Snackbar.make(
                            root,
                            "${menuItem.name} hiện không có sẵn",
                            com.google.android.material.snackbar.Snackbar.LENGTH_SHORT
                        ).show()
                    }
                }

                buttonAddToCart.setOnClickListener {
                    if (menuItem.isAvailable) {
                        onItemClick(menuItem)
                        // Show feedback
                        com.google.android.material.snackbar.Snackbar.make(
                            root,
                            "Đã thêm ${menuItem.name}",
                            com.google.android.material.snackbar.Snackbar.LENGTH_SHORT
                        ).show()
                    } else {
                        // Show not available message
                        com.google.android.material.snackbar.Snackbar.make(
                            root,
                            "${menuItem.name} hiện không có sẵn",
                            com.google.android.material.snackbar.Snackbar.LENGTH_SHORT
                        ).show()
                    }
                }
            }
        }
    }

    private class MenuDiffCallback : DiffUtil.ItemCallback<MenuItem>() {
        override fun areItemsTheSame(oldItem: MenuItem, newItem: MenuItem): Boolean {
            return oldItem.id == newItem.id
        }

        override fun areContentsTheSame(oldItem: MenuItem, newItem: MenuItem): Boolean {
            return oldItem == newItem
        }
    }
}
